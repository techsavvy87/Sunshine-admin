<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Checkout;
use App\Models\FacilityAddress;
use App\Models\Invoice;
use App\Models\InvoiceItem;

class LateFeeService
{
    public function reconcile(Appointment $appointment): array
    {
        $appointment->loadMissing('service.category');
        $checkout = Checkout::where('appointment_id', $appointment->id)->first();
        $flows = $checkout ? $this->flows($checkout->flows) : [];
        $previousFee = floatval($flows['applied_late_checkout_daycare_fee'] ?? 0);
        $facility = FacilityAddress::query()->orderBy('id')->first();
        $eligible = shouldApplyLateFee($facility, $appointment);
        $pricingAppointment = $appointment;
        if (($appointment->status ?? null) === 'finished') {
            $pricingAppointment = clone $appointment;
            $pricingAppointment->status = 'completed';
        }
        $breakdown = getBoardingLateCheckoutDaycareBreakdown($pricingAppointment, $checkout, 1);
        $fee = $eligible ? floatval($breakdown['fee'] ?? 0) : 0;

        $invoice = Invoice::where('appointment_id', $appointment->id)->first();
        $invoiceFee = 0;
        if ($invoice) {
            $lateItems = InvoiceItem::where('invoice_id', $invoice->id)->get()->filter(
                fn ($item) => in_array(strtolower(trim((string) $item->item_name)), ['late fee', 'late checkout daycare fee', 'late checkout fee'], true)
            );
            $invoiceFee = floatval($lateItems->max('price') ?? 0);
            InvoiceItem::whereIn('id', $lateItems->pluck('id'))->delete();

            if ($fee > 0) {
                $item = new InvoiceItem;
                $item->invoice_id = $invoice->id;
                $item->item_name = 'Late Fee';
                $item->price = $fee;
                $item->item_type = 'service';
                $item->save();
            }

            app(InvoicePaymentService::class)->syncInvoiceState($invoice->fresh());
        }

        if ($checkout) {
            $flows['applied_late_checkout_daycare_fee'] = $fee;
            $checkout->flows = json_encode($flows);
            $checkout->save();
        }

        $previousAppliedFee = max($previousFee, $invoiceFee);
        $appointment->estimated_price = round(max(0, floatval($appointment->estimated_price) - $previousAppliedFee) + $fee, 2);
        $appointment->save();

        return [
            'applies' => $eligible && $fee > 0,
            'fee' => $fee,
            'estimated_price' => floatval($appointment->estimated_price),
            'payment_summary' => $invoice ? app(InvoicePaymentService::class)->buildSummary($invoice->fresh()) : null,
        ];
    }

    private function flows($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
