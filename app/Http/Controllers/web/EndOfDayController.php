<?php

namespace App\Http\Controllers\web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Appointment;
use App\Models\Checkin;
use App\Models\Checkout;
use App\Models\Process;
use App\Models\IncidentReport;
use App\Models\CustomerComplaint;
use App\Models\Invoice;
use App\Models\EmployeeAttendance;
use App\Models\MaintenanceIssue;
use App\Models\User;
use App\Models\PetProfile;
use Carbon\Carbon;

class EndOfDayController extends Controller
{
    public function listEndOfDay(Request $request)
    {
        $dateStr = $request->get('date', now()->format('Y-m-d'));
        $date = Carbon::parse($dateStr);

        // --- PETS ---
        $checkedInCount = Checkin::whereDate('date', $date)->count();
        $noShowCancelledCount = Appointment::whereDate('date', $date)
            ->whereIn('status', ['no_show', 'cancelled'])->count();
        $checkoutCompletedCount = Checkout::whereDate('date', $date)->count();
        $appointmentIdsWithProcessToday = Process::whereDate('date', $date)->pluck('appointment_id');
        $appointmentIdsCheckedOutToday = Checkout::whereDate('date', $date)->pluck('appointment_id');
        $dogsOnProperty = Process::whereDate('date', $date)
            ->whereNotIn('appointment_id', $appointmentIdsCheckedOutToday)->count();

        $petDailyNumbers = [
            'checked_in' => $checkedInCount,
            'no_show_cancelled' => $noShowCancelledCount,
            'checkout_completed' => $checkoutCompletedCount,
            'dogs_on_property' => $dogsOnProperty,
        ];

        $incidentReports = IncidentReport::whereDate('created_at', $date)->get();
        foreach ($incidentReports as $report) {
            $report->pets = PetProfile::whereIn('id', array_filter(array_map('trim', explode(',', $report->pet_ids ?? ''))))->get();
            $report->staffs = User::whereIn('id', array_filter(array_map('trim', explode(',', $report->staff_ids ?? ''))))->with('profile')->get();
        }

        // --- PETS: Treatment List (Nose to tail check) from boarding process flows ---
        $bodyPartsMap = [
            'nose' => 'Nose',
            'ears' => 'Ears',
            'eyes' => 'Eyes',
            'mouth' => 'Mouth',
            'body_coat' => 'Body/Coat',
            'paws_feet' => 'Paws/Feet',
            'abdomen' => 'Abdomen',
            'digestive' => 'Digestive',
            'diarrhea' => 'Diarrhea',
        ];
        $treatmentListRows = [];
        $processesForDate = Process::whereDate('date', $date)->with(['appointment.pet', 'appointment.customer.profile'])->get();
        foreach ($processesForDate as $process) {
            $flows = $process->flows ? (is_string($process->flows) ? json_decode($process->flows, true) : $process->flows) : [];
            if (!is_array($flows)) {
                $flows = [];
            }
            $appointmentId = $process->appointment_id;
            $checkData = $flows['check_pet']['check_data'][$appointmentId] ?? [];
            $treatmentPlanData = $flows['treatment_plan']['treatment_data'][$appointmentId] ?? [];
            $completedTreatments = $flows['treatment_list']['completed_treatments'][$appointmentId] ?? false;

            $issues = [];
            if (is_array($checkData)) {
                foreach ($checkData as $partKey => $partData) {
                    if (is_array($partData) && ($partData['status'] ?? '') === 'issue') {
                        $issues[] = $bodyPartsMap[$partKey] ?? $partKey;
                    }
                }
            }
            // Only include pets that have at least one issue from nose-to-tail check
            if (empty($issues)) {
                continue;
            }
            $option = $treatmentPlanData['option'] ?? '';
            $optionLabel = $option === 'in-house' ? 'In-house' : ($option === 'vet-watch' ? 'Vet watch' : ($option ?: '—'));
            // Status from Treatments step (Treatment Lunch Rest): result = continue / resolved / escalate
            $treatmentsTlrResults = $flows['treatments_tlr']['results'][$appointmentId] ?? [];
            $resultVal = is_array($treatmentsTlrResults) ? ($treatmentsTlrResults['result'] ?? '') : '';
            $statusLabel = $resultVal === 'continue' ? 'Continue' : ($resultVal === 'resolved' ? 'Resolved' : ($resultVal === 'escalate' ? 'Escalate' : '—'));
            // Treatment from Treatment List (TLR) = treatment plan detail
            $treatmentDetail = $treatmentPlanData['detail'] ?? '';
            $pet = $process->appointment && $process->appointment->pet ? $process->appointment->pet : null;
            $customerProfile = $process->appointment && $process->appointment->customer ? $process->appointment->customer->profile : null;
            $treatmentListRows[] = [
                'appointment_id' => $appointmentId,
                'pet_name' => $pet ? $pet->name : 'N/A',
                'pet_img' => $pet && $pet->pet_img ? $pet->pet_img : null,
                'customer_name' => $customerProfile
                    ? trim($customerProfile->first_name . ' ' . $customerProfile->last_name)
                    : ($process->appointment && $process->appointment->customer ? $process->appointment->customer->name : '—'),
                'customer_avatar' => $customerProfile && $customerProfile->avatar_img ? $customerProfile->avatar_img : null,
                'issues' => $issues,
                'option' => $optionLabel,
                'detail' => $treatmentDetail,
                'completed' => $completedTreatments === true || $completedTreatments === 'true',
                'status' => $statusLabel,
                'treatment' => $treatmentDetail,
            ];
        }

        // Add DNE (Do not eat AM/PM meals) pets to Issues and Concerns
        $processesByAppointment = $processesForDate->keyBy('appointment_id');
        $treatmentListRowsByAppointment = collect($treatmentListRows)->keyBy('appointment_id');
        $reportsAmIds = [];
        $reportsPmIds = [];
        $reportsAmStatuses = [];
        $reportsPmStatuses = [];
        foreach ($processesForDate as $process) {
            $flows = $process->flows ? (is_string($process->flows) ? json_decode($process->flows, true) : $process->flows) : [];
            if (!is_array($flows)) {
                continue;
            }
            $reportsAm = $flows['reports_am'] ?? [];
            $reportsPm = $flows['reports_pm'] ?? [];
            $reportsAmIds = array_merge($reportsAmIds, (array) ($reportsAm['selected_pet_ids'] ?? []));
            $reportsPmIds = array_merge($reportsPmIds, (array) ($reportsPm['selected_pet_ids'] ?? []));
            if (isset($reportsAm['statuses']) && is_array($reportsAm['statuses'])) {
                $reportsAmStatuses = array_replace($reportsAmStatuses, $reportsAm['statuses']);
            }
            if (isset($reportsPm['statuses']) && is_array($reportsPm['statuses'])) {
                $reportsPmStatuses = array_replace($reportsPmStatuses, $reportsPm['statuses']);
            }
        }
        $reportsAmIds = array_values(array_unique(array_filter(array_map('intval', $reportsAmIds))));
        $reportsPmIds = array_values(array_unique(array_filter(array_map('intval', $reportsPmIds))));
        $dneAppointmentIds = array_unique(array_merge($reportsAmIds, $reportsPmIds));
        foreach ($dneAppointmentIds as $appointmentId) {
            $process = $processesByAppointment->get($appointmentId);
            if (!$process) {
                continue;
            }
            $dneIssues = [];
            if (in_array((int) $appointmentId, $reportsAmIds, true)) {
                $amStatus = strtolower(trim((string) ($reportsAmStatuses[$appointmentId] ?? $reportsAmStatuses[(string) $appointmentId] ?? '')));
                $dneIssues[] = $amStatus === 'partial_meal' ? 'Partial AM Meal' : 'Do not eat AM Meals';
            }
            if (in_array((int) $appointmentId, $reportsPmIds, true)) {
                $pmStatus = strtolower(trim((string) ($reportsPmStatuses[$appointmentId] ?? $reportsPmStatuses[(string) $appointmentId] ?? '')));
                $dneIssues[] = $pmStatus === 'partial_meal' ? 'Partial PM Meal' : 'Do not eat PM Meals';
            }
            $pet = $process->appointment && $process->appointment->pet ? $process->appointment->pet : null;
            $customerProfile = $process->appointment && $process->appointment->customer ? $process->appointment->customer->profile : null;
            $existingRow = $treatmentListRowsByAppointment->get($appointmentId);
            if ($existingRow) {
                $existingRow['issues'] = array_merge($existingRow['issues'], $dneIssues);
                $treatmentListRowsByAppointment->put($appointmentId, $existingRow);
            } else {
                $flows = $process->flows ? (is_string($process->flows) ? json_decode($process->flows, true) : $process->flows) : [];
                $flows = is_array($flows) ? $flows : [];
                $treatmentsTlrResults = ($flows['treatments_tlr']['results'] ?? [])[$appointmentId] ?? [];
                $resultVal = is_array($treatmentsTlrResults) ? ($treatmentsTlrResults['result'] ?? '') : '';
                $statusLabel = $resultVal === 'continue' ? 'Continue' : ($resultVal === 'resolved' ? 'Resolved' : ($resultVal === 'escalate' ? 'Escalate' : '—'));
                $treatmentListRowsByAppointment->put($appointmentId, [
                    'appointment_id' => $appointmentId,
                    'pet_name' => $pet ? $pet->name : 'N/A',
                    'pet_img' => $pet && $pet->pet_img ? $pet->pet_img : null,
                    'customer_name' => $customerProfile
                        ? trim($customerProfile->first_name . ' ' . $customerProfile->last_name)
                        : ($process->appointment && $process->appointment->customer ? $process->appointment->customer->name : '—'),
                    'customer_avatar' => $customerProfile && $customerProfile->avatar_img ? $customerProfile->avatar_img : null,
                    'issues' => $dneIssues,
                    'option' => '—',
                    'detail' => '',
                    'completed' => false,
                    'status' => $statusLabel,
                    'treatment' => '',
                ]);
            }
        }
        $treatmentListRows = $treatmentListRowsByAppointment->values()->all();

        // --- PEOPLE: Customers ---
        $complaintsToday = CustomerComplaint::whereDate('date', $date)->with('customer.profile')->orderBy('date')->get();
        $invoicesToday = Invoice::whereDate('issued_at', $date)->orderBy('issued_at')->get();
        $invoicesByStatus = $invoicesToday->groupBy('status')->map->count();

        // --- PEOPLE: Employees ---
        $attendanceRecords = EmployeeAttendance::whereDate('date', $date)->with('user.profile')->get();
        $incidentsToday = $incidentReports; // already loaded, have staff_ids

        // --- PROPERTY: Maintenance ---
        $maintenanceIssues = MaintenanceIssue::whereDate('date', $date)->get();
        $maintenanceByType = $maintenanceIssues->groupBy('type');
        $facilityIssues = $maintenanceByType->get('facility', collect());
        $equipmentIssues = $maintenanceByType->get('equipment', collect());

        $data = [
            'date' => $date,
            'petDailyNumbers' => $petDailyNumbers,
            'incidentReports' => $incidentReports,
            'treatmentListRows' => $treatmentListRows,
            'complaintsToday' => $complaintsToday,
            'invoicesToday' => $invoicesToday,
            'invoicesByStatus' => $invoicesByStatus,
            'attendanceRecords' => $attendanceRecords,
            'incidentsToday' => $incidentsToday,
            'facilityIssues' => $facilityIssues,
            'equipmentIssues' => $equipmentIssues,
            'maintenanceIssues' => $maintenanceIssues,
        ];

        if ($request->get('embed')) {
            return response()->view('reports.partials.end-of-day-content', array_merge($data, ['embed' => true]));
        }

        return view('reports.end-of-day', $data);
    }

    public function createEndOfDayMaintenance(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'type' => 'required|in:facility,equipment',
            'description' => 'required|string|max:65535',
        ]);
        MaintenanceIssue::create([
            'date' => $request->date,
            'type' => $request->type,
            'description' => $request->description,
            'status' => 'open',
        ]);
        return redirect()->route('end-of-day', ['date' => $request->date])->with('message', 'Maintenance issue added.');
    }
}
