<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Boarding Report - {{ $appointment->pet->name }}</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    body {
      font-family: 'DejaVu Sans', Arial, sans-serif;
      font-size: 11px;
      line-height: 1.5;
      color: #333;
      padding: 20px;
    }
    h1 {
      font-size: 20px;
      margin-bottom: 15px;
      color: #1a1a1a;
      border-bottom: 2px solid #4a5568;
      padding-bottom: 8px;
    }
    h2 {
      font-size: 14px;
      margin-top: 15px;
      margin-bottom: 8px;
      color: #2d3748;
      font-weight: bold;
    }
    h3 {
      font-size: 12px;
      margin-top: 10px;
      margin-bottom: 5px;
      color: #4a5568;
      font-weight: bold;
    }
    .section {
      margin-bottom: 15px;
      padding: 10px;
      border-bottom: 1px solid #e2e8f0;
      page-break-inside: avoid;
    }
    .field {
      margin-bottom: 5px;
    }
    .field-label {
      font-weight: bold;
      color: #4a5568;
      display: inline-block;
      min-width: 120px;
    }
    .field-value {
      color: #1a1a1a;
      display: inline-block;
    }
    .grid {
      display: table;
      width: 100%;
      margin-bottom: 10px;
    }
    .grid-row {
      display: table-row;
    }
    .grid-cell {
      display: table-cell;
      padding: 3px 10px 3px 0;
      vertical-align: top;
    }
    .list-item {
      margin-bottom: 4px;
      padding-left: 15px;
      position: relative;
    }
    .list-item::before {
      content: "•";
      position: absolute;
      left: 0;
      color: #4a5568;
    }
    .checkbox-item {
      display: inline-block;
      margin-right: 15px;
    }
    .notes {
      white-space: pre-wrap;
      margin-top: 5px;
    }
    @media print {
      .section {
        page-break-inside: avoid;
      }
    }
    @page {
      margin: 20px;
    }
  </style>
</head>
<body>
  <h1>Boarding Report</h1>
  
  <!-- 1. Check-in -->
  @if(isset($checkin) && $checkin)
  <div class="section">
    <h2>1. Check-in</h2>
    <div class="grid">
      <div class="grid-row">
        <div class="grid-cell">
          <span class="field-label">Date:</span>
          <span class="field-value">{{ $checkin->date ? \Carbon\Carbon::parse($checkin->date)->format('M j, Y') : 'Not set' }}</span>
        </div>
      </div>
      <div class="grid-row">
        <div class="grid-cell">
          <span class="field-label">Start Time:</span>
          <span class="field-value">
            @if($appointment->start_time)
              @php
                try {
                  $time = \Carbon\Carbon::createFromFormat('H:i:s', $appointment->start_time)->format('g:i A');
                  echo $time;
                } catch (\Exception $e) {
                  echo $appointment->start_time;
                }
              @endphp
            @else
              Not set
            @endif
          </span>
        </div>
      </div>
      <div class="grid-row">
        <div class="grid-cell">
          <span class="field-label">Pickup Time:</span>
          <span class="field-value">
            @if($appointment->end_time)
              @php
                try {
                  $time = \Carbon\Carbon::createFromFormat('H:i:s', $appointment->end_time)->format('g:i A');
                  echo $time;
                } catch (\Exception $e) {
                  echo $appointment->end_time;
                }
              @endphp
            @else
              Not set
            @endif
          </span>
        </div>
      </div>
      @if($checkin->notes)
      <div class="grid-row">
        <div class="grid-cell">
          <span class="field-label">Notes:</span>
          <span class="field-value">{{ $checkin->notes }}</span>
        </div>
      </div>
      @endif
    </div>
  </div>
  @endif

  @if(isset($checkin) && $checkin && $checkin->flows && is_array($checkin->flows))
  <!-- Trip Information -->
  @if(isset($checkin->flows['pickup_datetime']) || isset($checkin->flows['trip_location']) || isset($checkin->flows['trip_phone']) || isset($checkin->flows['alternate_contact_name']) || isset($checkin->flows['alternate_contact_phone']) || isset($checkin->flows['trip_notes']))
  <div class="section">
    <h3>Trip Information</h3>
    @if(isset($checkin->flows['pickup_datetime']) && $checkin->flows['pickup_datetime'])
    <div class="field">
      <span class="field-label">Confirm pickup date and time:</span>
      <span class="field-value">
        @php
          try {
            $dt = \Carbon\Carbon::parse($checkin->flows['pickup_datetime']);
            echo $dt->format('M j, Y g:i A');
          } catch (\Exception $e) {
            echo $checkin->flows['pickup_datetime'];
          }
        @endphp
      </span>
    </div>
    @endif
    @if(isset($checkin->flows['trip_location']) && $checkin->flows['trip_location'])
    <div class="field">
      <span class="field-label">Trip location:</span>
      <span class="field-value">{{ $checkin->flows['trip_location'] }}</span>
    </div>
    @endif
    @if(isset($checkin->flows['trip_phone']) && $checkin->flows['trip_phone'])
    <div class="field">
      <span class="field-label">Trip phone number:</span>
      <span class="field-value">{{ $checkin->flows['trip_phone'] }}</span>
    </div>
    @endif
    @if((isset($checkin->flows['alternate_contact_name']) && $checkin->flows['alternate_contact_name']) || (isset($checkin->flows['alternate_contact_phone']) && $checkin->flows['alternate_contact_phone']))
    <div class="field">
      <span class="field-label">Alternate contact:</span>
      <span class="field-value">
        @if(isset($checkin->flows['alternate_contact_name']) && $checkin->flows['alternate_contact_name'])
          {{ $checkin->flows['alternate_contact_name'] }}
        @endif
        @if(isset($checkin->flows['alternate_contact_phone']) && $checkin->flows['alternate_contact_phone'])
          @if(isset($checkin->flows['alternate_contact_name']) && $checkin->flows['alternate_contact_name'])
            - 
          @endif
          {{ $checkin->flows['alternate_contact_phone'] }}
        @endif
      </span>
    </div>
    @endif
    @if(isset($checkin->flows['trip_notes']) && $checkin->flows['trip_notes'])
    <div class="field">
      <span class="field-label">Notes (authorized pickup & payment arrangement):</span>
      <span class="field-value notes">{{ $checkin->flows['trip_notes'] }}</span>
    </div>
    @endif
  </div>
  @endif

  <!-- Pet Information -->
  @if(isset($checkin->flows['has_leash']) || isset($checkin->flows['has_collar']) || isset($checkin->flows['has_other_items']) || isset($checkin->flows['other_items_description']))
  <div class="section">
    <h3>Pet Information</h3>
    @if(isset($checkin->flows['other_items_description']) && $checkin->flows['other_items_description'])
    <div class="field">
      <span class="field-label">Items:</span>
      <span class="field-value notes">{{ $checkin->flows['other_items_description'] }}</span>
    </div>
    @endif
  </div>
  @endif

  <!-- Dispense Information -->
  @if(isset($checkin->flows['food_brand']) || isset($checkin->flows['feeding_am']) || isset($checkin->flows['feeding_pm']) || isset($checkin->flows['food_quantity']) || isset($checkin->flows['food_starting_amount']) || isset($checkin->flows['food_description']) || isset($checkin->flows['additional_feedings']) || isset($checkin->flows['additional_feedings_am']) || isset($checkin->flows['additional_feedings_pm']) || isset($checkin->flows['medications']) || isset($checkin->flows['medications_am']) || isset($checkin->flows['medications_pm']) || (isset($checkin->flows['meds_list']) && is_array($checkin->flows['meds_list']) && count($checkin->flows['meds_list']) > 0))
  <div class="section">
    <h3>Dispense Information</h3>
    @if(isset($checkin->flows['food_brand']) && $checkin->flows['food_brand'])
    <div class="field">
      <span class="field-label">Food (name of brand):</span>
      <span class="field-value">{{ $checkin->flows['food_brand'] }}</span>
    </div>
    @endif
    @if(isset($checkin->flows['feeding_am']) || isset($checkin->flows['feeding_pm']) || (isset($checkin->flows['feeding_time']) && $checkin->flows['feeding_time']))
    <div class="field">
      <span class="field-label">Feeding:</span>
      <span class="field-value">
        @php
          $feedingAm = false;
          $feedingPm = false;
          if (isset($checkin->flows['feeding_am']) && ($checkin->flows['feeding_am'] === true || $checkin->flows['feeding_am'] === 'true')) {
            $feedingAm = true;
          }
          if (isset($checkin->flows['feeding_pm']) && ($checkin->flows['feeding_pm'] === true || $checkin->flows['feeding_pm'] === 'true')) {
            $feedingPm = true;
          }
          if (!$feedingAm && !$feedingPm && isset($checkin->flows['feeding_time'])) {
            $feedingTime = $checkin->flows['feeding_time'];
            if ($feedingTime === 'AM' || $feedingTime === 'AM/PM') {
              $feedingAm = true;
            }
            if ($feedingTime === 'PM' || $feedingTime === 'AM/PM') {
              $feedingPm = true;
            }
          }
        @endphp
        <span class="checkbox-item">{{ $feedingAm ? '☑' : '☐' }} AM</span>
        <span class="checkbox-item">{{ $feedingPm ? '☑' : '☐' }} PM</span>
      </span>
    </div>
    @endif
    @if(isset($checkin->flows['food_quantity']) && $checkin->flows['food_quantity'])
    <div class="field">
      <span class="field-label">Quantity:</span>
      <span class="field-value">{{ $checkin->flows['food_quantity'] }}</span>
    </div>
    @endif
    @if(isset($checkin->flows['food_starting_amount']) && $checkin->flows['food_starting_amount'])
    <div class="field">
      <span class="field-label">Starting amount:</span>
      <span class="field-value">{{ $checkin->flows['food_starting_amount'] }}</span>
    </div>
    @endif
    @if(isset($checkin->flows['food_description']) && $checkin->flows['food_description'])
    <div class="field">
      <span class="field-label">Description:</span>
      <span class="field-value notes">{{ $checkin->flows['food_description'] }}</span>
    </div>
    @endif
    @if(isset($checkin->flows['additional_feedings']) || isset($checkin->flows['additional_feedings_am']) || isset($checkin->flows['additional_feedings_pm']))
    <div class="field">
      <span class="field-label">Additional feedings:</span>
      <span class="field-value">
        <span class="checkbox-item">{{ isset($checkin->flows['additional_feedings_am']) && ($checkin->flows['additional_feedings_am'] === true || $checkin->flows['additional_feedings_am'] === 'true') ? '☑' : '☐' }} AM</span>
        <span class="checkbox-item">{{ isset($checkin->flows['additional_feedings_pm']) && ($checkin->flows['additional_feedings_pm'] === true || $checkin->flows['additional_feedings_pm'] === 'true') ? '☑' : '☐' }} PM</span>
      </span>
      @if(isset($checkin->flows['additional_feedings']) && $checkin->flows['additional_feedings'])
      <div class="notes" style="margin-left: 120px; margin-top: 3px;">{{ $checkin->flows['additional_feedings'] }}</div>
      @endif
    </div>
    @endif
    @php
      $medicationRows = isset($checkin->flows['meds_list']) && is_array($checkin->flows['meds_list']) ? $checkin->flows['meds_list'] : [];
    @endphp
    @if(count($medicationRows) > 0)
    <div class="field">
      <span class="field-label">Medications:</span>
      <div class="field-value notes" style="margin-left: 120px; margin-top: 3px;">
        @foreach($medicationRows as $medicationRow)
          @php
            if (!is_array($medicationRow)) {
              continue;
            }

            $conditionValue = $medicationRow['condition'] ?? '';
            $mealConditionValue = $medicationRow['meal_condition'] ?? $conditionValue;
            if ($mealConditionValue === 'after_meals') {
              $mealConditionValue = 'after_meal';
            }
            $conditionLabel = match ($mealConditionValue) {
              'after_meal' => 'After Meal',
              'before_meal' => 'Before Meal',
              'empty_stomach' => 'Empty Stomach',
              default => '',
            };
            $customTime = $medicationRow['custom_time'] ?? '';
            $dispenseLabels = [];
            if (!empty($medicationRow['dispense_am']) && ($medicationRow['dispense_am'] === true || $medicationRow['dispense_am'] === 'true')) {
              $dispenseLabels[] = 'AM';
            }
            if (!empty($medicationRow['dispense_pm']) && ($medicationRow['dispense_pm'] === true || $medicationRow['dispense_pm'] === 'true')) {
              $dispenseLabels[] = 'PM';
            }
            if (!empty($medicationRow['dispense_rest']) && ($medicationRow['dispense_rest'] === true || $medicationRow['dispense_rest'] === 'true')) {
              $dispenseLabels[] = 'Rest';
            }
            if (!empty($medicationRow['dispense_before_bed']) && ($medicationRow['dispense_before_bed'] === true || $medicationRow['dispense_before_bed'] === 'true')) {
              $dispenseLabels[] = 'Before Bed';
            }
            if (!empty($medicationRow['dispense_prn']) && ($medicationRow['dispense_prn'] === true || $medicationRow['dispense_prn'] === 'true')) {
              $dispenseLabels[] = 'PRN';
            }
            if (!empty($medicationRow['dispense_custom_time']) && ($medicationRow['dispense_custom_time'] === true || $medicationRow['dispense_custom_time'] === 'true')) {
              $dispenseLabels[] = !empty($customTime) ? 'Custom Time (' . $customTime . ')' : 'Custom Time';
            }
            if (empty($dispenseLabels) && $conditionValue === 'before_sleep') {
              $dispenseLabels[] = 'Before Bed';
            }
          @endphp
          <div style="margin-bottom: 4px;">
            <strong>Name:</strong> {{ $medicationRow['name'] ?? '-' }} | <strong>Dosage/Instruction:</strong> {{ $medicationRow['amount'] ?? '-' }} | <strong>Dispense:</strong> {{ count($dispenseLabels) ? implode(', ', $dispenseLabels) : '-' }} | <strong>Meal Condition:</strong> {{ $conditionLabel ?: '-' }}
          </div>
        @endforeach
      </div>
    </div>
    @elseif(isset($checkin->flows['medications']) || isset($checkin->flows['medications_am']) || isset($checkin->flows['medications_pm']))
    <div class="field">
      <span class="field-label">Medications:</span>
      <span class="field-value">
        <span class="checkbox-item">{{ isset($checkin->flows['medications_am']) && ($checkin->flows['medications_am'] === true || $checkin->flows['medications_am'] === 'true') ? '☑' : '☐' }} AM</span>
        <span class="checkbox-item">{{ isset($checkin->flows['medications_pm']) && ($checkin->flows['medications_pm'] === true || $checkin->flows['medications_pm'] === 'true') ? '☑' : '☐' }} PM</span>
      </span>
      @if(isset($checkin->flows['medications']) && $checkin->flows['medications'])
      <div class="notes" style="margin-left: 120px; margin-top: 3px;">{{ $checkin->flows['medications'] }}</div>
      @endif
    </div>
    @endif
  </div>
  @endif

  <!-- Assignment or location for visit -->
  @if(isset($checkin->flows['location_type']) || isset($checkin->flows['location_details']))
  <div class="section">
    <h3>Assignment or location for visit</h3>
    @if(isset($checkin->flows['location_type']) && $checkin->flows['location_type'])
    <div class="field">
      <span class="field-label">Location type:</span>
      <span class="field-value">{{ ucfirst($checkin->flows['location_type']) }}</span>
    </div>
    @endif
    @if(isset($checkin->flows['location_details']) && $checkin->flows['location_details'])
    <div class="field">
      <span class="field-label">Location details:</span>
      <span class="field-value">{{ $checkin->flows['location_details'] }}</span>
    </div>
    @endif
  </div>
  @endif
  @endif

  <!-- 2. Issues -->
  @if(isset($processes) && $processes->count() > 0)
  <div class="section">
    <h2>2. Issues</h2>
    @php
      $processTableRows = [];
      $appointmentId = $appointment->id;
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
      $conciergeItems = ['nose', 'eyes', 'ears', 'mouth', 'skin', 'paws', 'tail', 'genitals', 'overall'];

      foreach ($processes as $processItem) {
        $processDate = $processItem->date ? \Carbon\Carbon::parse($processItem->date)->format('M j, Y') : '';

        $defaultRowTime = '—';
        if ($processItem->start_time) {
          try {
            $defaultRowTime = \Carbon\Carbon::createFromFormat('H:i:s', $processItem->start_time)->format('g:i A');
          } catch (\Exception $e) {
            try {
              $defaultRowTime = \Carbon\Carbon::createFromFormat('H:i', $processItem->start_time)->format('g:i A');
            } catch (\Exception $e2) {
              $defaultRowTime = (string) $processItem->start_time;
            }
          }
        }

        $rowIssues = [];
        $rowTreatmentStrings = [];
        $statusLabel = '—';
        $flows = $processItem->flows;
        if (!is_array($flows)) {
          $flows = [];
        }
        $hasNoseTailIssues = false;

        if (!empty($flows)) {
          $reportsAmIds = array_map('intval', (array) ($flows['reports_am']['selected_pet_ids'] ?? []));
          $reportsPmIds = array_map('intval', (array) ($flows['reports_pm']['selected_pet_ids'] ?? []));
          if (in_array((int) $appointmentId, $reportsAmIds, true)) {
            $rowIssues[] = 'Do not eat AM Meals';
          }
          if (in_array((int) $appointmentId, $reportsPmIds, true)) {
            $rowIssues[] = 'Do not eat PM Meals';
          }
          $checkData = $flows['check_pet']['check_data'][$appointmentId] ?? [];
          if (is_array($checkData)) {
            foreach ($checkData as $partKey => $partData) {
              if (is_array($partData) && ($partData['status'] ?? '') === 'issue') {
                $rowIssues[] = $bodyPartsMap[$partKey] ?? ucfirst(str_replace('_', ' ', $partKey));
                $hasNoseTailIssues = true;
              }
            }
          }

          $hasTreatmentIssues = false;
          if (!empty($flows['treatment_issues']) && is_array($flows['treatment_issues'])) {
            foreach ($flows['treatment_issues'] as $issue) {
              if (!empty($issue['issue'])) {
                $rowIssues[] = ucfirst(str_replace('_', ' ', $issue['issue']));
                $hasTreatmentIssues = true;
              }
            }
          }
          foreach ($conciergeItems as $item) {
            if (isset($flows['concierge_' . $item]) && $flows['concierge_' . $item] === 'issue') {
              $issueDetails = [];
              $issueFields = [];
              switch ($item) {
                case 'nose': $issueFields = ['discharge', 'dryness', 'cracking']; break;
                case 'eyes': $issueFields = ['redness', 'cloudiness', 'discharge']; break;
                case 'ears': $issueFields = ['odor', 'redness', 'swelling', 'buildup']; break;
                case 'mouth': $issueFields = ['tartar', 'broken_teeth', 'foul_breath']; break;
                case 'skin': $issueFields = ['dryness', 'irritation', 'hot_spots', 'lumps']; break;
                case 'paws': $issueFields = ['cracking', 'irritation', 'swelling']; break;
                case 'tail': $issueFields = ['irritation', 'swelling']; break;
                case 'genitals': $issueFields = ['discharge', 'irritation', 'swelling']; break;
                default: $issueFields = [];
              }
              foreach ($issueFields as $field) {
                if (!empty($flows['concierge_' . $item . '_' . $field]) && ($flows['concierge_' . $item . '_' . $field] === true || $flows['concierge_' . $item . '_' . $field] === 'true')) {
                  $issueDetails[] = ucfirst(str_replace('_', ' ', $field));
                }
              }
              $treatmentText = ucfirst($item);
              if (count($issueDetails) > 0) {
                $treatmentText .= ' (' . implode(', ', $issueDetails) . ')';
              }
              $rowIssues[] = $treatmentText;
              $hasNoseTailIssues = true;
            }
          }
          if (!empty($flows['am_meal_dispense_not_eating']) && ($flows['am_meal_dispense_not_eating'] === true || $flows['am_meal_dispense_not_eating'] === 'true')) {
            if (!in_array('Do not eat AM Meals', $rowIssues, true)) {
              $rowIssues[] = 'Do not eat AM Meals';
            }
          }
          if (!empty($flows['pm_meal_dispense_not_eating']) && ($flows['pm_meal_dispense_not_eating'] === true || $flows['pm_meal_dispense_not_eating'] === 'true')) {
            if (!in_array('Do not eat PM Meals', $rowIssues, true)) {
              $rowIssues[] = 'Do not eat PM Meals';
            }
          }

          $treatmentsTlrResults = $flows['treatments_tlr']['results'][$appointmentId] ?? [];
          $resultVal = is_array($treatmentsTlrResults) ? ($treatmentsTlrResults['result'] ?? '') : '';
          $statusLabel = $resultVal === 'continue' ? 'Continue' : ($resultVal === 'resolved' ? 'Resolved' : ($resultVal === 'escalate' ? 'Escalate' : '—'));

          $treatmentPlanData = $flows['treatment_plan']['treatment_data'][$appointmentId] ?? [];
          $treatmentDetail = is_array($treatmentPlanData) ? ($treatmentPlanData['detail'] ?? '') : '';
          if ($treatmentDetail !== '') {
            $rowTreatmentStrings[] = $treatmentDetail;
          }
          if (!empty($flows['treatment_issues']) && is_array($flows['treatment_issues'])) {
            foreach ($flows['treatment_issues'] as $issue) {
              if (!empty($issue['inhouse_treatment'])) {
                $rowTreatmentStrings[] = 'In-house: ' . $issue['inhouse_treatment'];
              }
              if (!empty($issue['vet_treatment'])) {
                $rowTreatmentStrings[] = 'Vet: ' . $issue['vet_treatment'];
              }
            }
          }
          foreach ($conciergeItems as $item) {
            if (isset($flows['concierge_' . $item]) && $flows['concierge_' . $item] === 'issue' && !empty($flows['concierge_' . $item . '_notes'])) {
              $rowTreatmentStrings[] = ucfirst($item) . ': ' . $flows['concierge_' . $item . '_notes'];
            }
          }
          if (!empty($flows['am_meal_dispense_must_eat'])) {
            $rowTreatmentStrings[] = 'AM must eat: ' . $flows['am_meal_dispense_must_eat'];
          }
          if (!empty($flows['am_med_dispense_instructions'])) {
            $rowTreatmentStrings[] = 'AM instructions: ' . $flows['am_med_dispense_instructions'];
          }
          if (!empty($flows['pm_meal_dispense_must_eat'])) {
            $rowTreatmentStrings[] = 'PM must eat: ' . $flows['pm_meal_dispense_must_eat'];
          }
          if (!empty($flows['pm_med_dispense_instructions'])) {
            $rowTreatmentStrings[] = 'PM instructions: ' . $flows['pm_med_dispense_instructions'];
          }
          if (!empty($flows['am_med_dispense_must_receive']) && ($flows['am_med_dispense_must_receive'] === true || $flows['am_med_dispense_must_receive'] === 'true')) {
            $rowTreatmentStrings[] = 'AM meds dispensed';
          }
          if (!empty($flows['pm_med_dispense_must_receive']) && ($flows['pm_med_dispense_must_receive'] === true || $flows['pm_med_dispense_must_receive'] === 'true')) {
            $rowTreatmentStrings[] = 'PM meds dispensed';
          }
        }

        $rowTime = $defaultRowTime;
        if (!empty($flows)) {
          $parseTime = function($t) {
            if (!$t || trim((string)$t) === '') return null;
            try {
              return \Carbon\Carbon::parse($t)->format('g:i A');
            } catch (\Exception $e) {
              return null;
            }
          };
          if ($hasNoseTailIssues && !empty($flows['nose_tail_time'])) {
            $rowTime = $parseTime($flows['nose_tail_time']) ?: $rowTime;
          } elseif (in_array('Do not eat AM Meals', $rowIssues, true)) {
            $ram = $flows['reports_am'] ?? null;
            $t = (is_array($ram) ? ($ram['process_time'] ?? $ram['processTime'] ?? null) : null) ?: ($flows['am_meal_dispense_time'] ?? null);
            if ($t) $rowTime = $parseTime($t) ?: $rowTime;
          } elseif (in_array('Do not eat PM Meals', $rowIssues, true)) {
            $rpm = $flows['reports_pm'] ?? null;
            $t = (is_array($rpm) ? ($rpm['process_time'] ?? $rpm['processTime'] ?? null) : null) ?: ($flows['pm_meal_dispense_time'] ?? null);
            if ($t) $rowTime = $parseTime($t) ?: $rowTime;
          } elseif (!empty($hasTreatmentIssues)) {
            $ttlr = $flows['treatments_tlr'] ?? null;
            $tlist = $flows['treatment_list_tlr'] ?? null;
            $t = (is_array($ttlr) ? ($ttlr['process_time'] ?? $ttlr['processTime'] ?? null) : null) ?: (is_array($tlist) ? ($tlist['process_time'] ?? $tlist['processTime'] ?? null) : null);
            if ($t) $rowTime = $parseTime($t) ?: $rowTime;
          }
          if ($rowTime === $defaultRowTime && !empty($flows['rest_1200_time'])) {
            $rowTime = $parseTime($flows['rest_1200_time']) ?: $rowTime;
          }
        }
        $dateTimeDisplay = $processDate ?: '—';
        if ($rowTime !== '—') {
          $dateTimeDisplay = trim($dateTimeDisplay . ' ' . $rowTime);
        }

        $processTableRows[] = [
          'date_time' => $dateTimeDisplay,
          'issues' => $rowIssues,
          'status' => $statusLabel,
          'treatment' => count($rowTreatmentStrings) > 0 ? implode('; ', $rowTreatmentStrings) : '—',
        ];
      }
    @endphp

    <table class="process-table" style="width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 11px;">
      <thead>
        <tr>
          <th style="border: 1px solid #e2e8f0; padding: 6px 8px; background: #f7fafc; font-weight: bold; color: #2d3748;">Date/Time</th>
          <th style="border: 1px solid #e2e8f0; padding: 6px 8px; background: #f7fafc; font-weight: bold; color: #2d3748;">Issues</th>
          <th style="border: 1px solid #e2e8f0; padding: 6px 8px; background: #f7fafc; font-weight: bold; color: #2d3748;">Status</th>
          <th style="border: 1px solid #e2e8f0; padding: 6px 8px; background: #f7fafc; font-weight: bold; color: #2d3748;">Treatment</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($processTableRows as $row)
        <tr>
          <td style="border: 1px solid #e2e8f0; padding: 6px 8px; color: #1a1a1a;">{{ $row['date_time'] }}</td>
          <td style="border: 1px solid #e2e8f0; padding: 6px 8px; color: #1a1a1a;">{{ count($row['issues']) > 0 ? implode(', ', $row['issues']) : '—' }}</td>
          <td style="border: 1px solid #e2e8f0; padding: 6px 8px; color: #1a1a1a;">{{ $row['status'] }}</td>
          <td style="border: 1px solid #e2e8f0; padding: 6px 8px; color: #1a1a1a;">{{ $row['treatment'] }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @endif

  <!-- Additional Services -->
  @if($appointment->additional_service_ids)
    @php
      $additionalIds = explode(',', $appointment->additional_service_ids);
      $additionalServices = \App\Models\Service::whereIn('id', $additionalIds)->get();
    @endphp
    @if($additionalServices->count() > 0)
      @php
        // Check if any process has additional service details
        $hasAdditionalServiceDetails = false;
        if (isset($processes) && $processes->count() > 0) {
          foreach ($processes as $processItem) {
            if ($processItem->flows && is_array($processItem->flows)) {
              foreach ($additionalServices as $addService) {
                if (isset($processItem->flows['additional_service_' . $addService->id . '_start_time']) ||
                    isset($processItem->flows['additional_service_' . $addService->id . '_end_time']) ||
                    isset($processItem->flows['additional_service_' . $addService->id . '_notes'])) {
                  $hasAdditionalServiceDetails = true;
                  break 2;
                }
              }
            }
          }
        }
      @endphp
      @if($hasAdditionalServiceDetails)
      <div class="section">
        <h2>Additional Services</h2>
        @foreach($additionalServices as $addService)
          @php
            // Collect data from all processes for this service
            $serviceStartTimes = [];
            $serviceEndTimes = [];
            $serviceNotes = [];
            
            if (isset($processes) && $processes->count() > 0) {
              foreach ($processes as $processItem) {
                if ($processItem->flows && is_array($processItem->flows)) {
                  $processDate = $processItem->date ? \Carbon\Carbon::parse($processItem->date)->format('M j, Y') : '';
                  
                  if (isset($processItem->flows['additional_service_' . $addService->id . '_start_time']) && $processItem->flows['additional_service_' . $addService->id . '_start_time']) {
                    $serviceStartTimes[] = [
                      'time' => $processItem->flows['additional_service_' . $addService->id . '_start_time'],
                      'date' => $processDate
                    ];
                  }
                  if (isset($processItem->flows['additional_service_' . $addService->id . '_end_time']) && $processItem->flows['additional_service_' . $addService->id . '_end_time']) {
                    $serviceEndTimes[] = [
                      'time' => $processItem->flows['additional_service_' . $addService->id . '_end_time'],
                      'date' => $processDate
                    ];
                  }
                  if (isset($processItem->flows['additional_service_' . $addService->id . '_notes']) && $processItem->flows['additional_service_' . $addService->id . '_notes']) {
                    $serviceNotes[] = [
                      'notes' => $processItem->flows['additional_service_' . $addService->id . '_notes'],
                      'date' => $processDate
                    ];
                  }
                }
              }
            }
          @endphp
          @if(count($serviceStartTimes) > 0 || count($serviceEndTimes) > 0 || count($serviceNotes) > 0)
          <div style="margin-top: 8px;">
            <h3>{{ $addService->name }}</h3>
            @if(count($serviceStartTimes) > 0)
            <div class="field">
              <span class="field-label">Start Time:</span>
              <div style="margin-left: 120px;">
                @foreach($serviceStartTimes as $startTime)
                  <div>
                    @if($startTime['date'])
                      {{ $startTime['date'] }}
                      @if($startTime['time'])
                        @php
                          try {
                            $timeFormatted = \Carbon\Carbon::createFromFormat('H:i', $startTime['time'])->format('g:i A');
                            echo ' ' . $timeFormatted;
                          } catch (\Exception $e) {
                            echo ' ' . $startTime['time'];
                          }
                        @endphp
                      @endif
                    @elseif($startTime['time'])
                      @php
                        try {
                          $timeFormatted = \Carbon\Carbon::createFromFormat('H:i', $startTime['time'])->format('g:i A');
                          echo $timeFormatted;
                        } catch (\Exception $e) {
                          echo $startTime['time'];
                        }
                      @endphp
                    @endif
                  </div>
                @endforeach
              </div>
            </div>
            @endif
            @if(count($serviceEndTimes) > 0)
            <div class="field">
              <span class="field-label">End Time:</span>
              <div style="margin-left: 120px;">
                @foreach($serviceEndTimes as $endTime)
                  <div>
                    @if($endTime['date'])
                      {{ $endTime['date'] }}
                      @if($endTime['time'])
                        @php
                          try {
                            $timeFormatted = \Carbon\Carbon::createFromFormat('H:i', $endTime['time'])->format('g:i A');
                            echo ' ' . $timeFormatted;
                          } catch (\Exception $e) {
                            echo ' ' . $endTime['time'];
                          }
                        @endphp
                      @endif
                    @elseif($endTime['time'])
                      @php
                        try {
                          $timeFormatted = \Carbon\Carbon::createFromFormat('H:i', $endTime['time'])->format('g:i A');
                          echo $timeFormatted;
                        } catch (\Exception $e) {
                          echo $endTime['time'];
                        }
                      @endphp
                    @endif
                  </div>
                @endforeach
              </div>
            </div>
            @endif
            @if(count($serviceNotes) > 0)
            <div class="field">
              <span class="field-label">Notes:</span>
              <div style="margin-left: 120px;">
                @foreach($serviceNotes as $note)
                  <div>
                    @if($note['date'])
                      <div>{{ $note['date'] }}</div>
                    @endif
                    @if($note['notes'])
                      <div class="notes" style="margin-left: 10px;">{{ $note['notes'] }}</div>
                    @endif
                  </div>
                @endforeach
              </div>
            </div>
            @endif
          </div>
          @endif
        @endforeach
      </div>
      @endif
    @endif
  @endif

  <!-- 3. Checkout -->
  @if(isset($checkout) && $checkout)
  <div class="section">
    <h2>3. Checkout</h2>
    <div class="grid">
      <div class="grid-row">
        <div class="grid-cell">
          <span class="field-label">Date:</span>
          <span class="field-value">{{ $checkout->date ? \Carbon\Carbon::parse($checkout->date)->format('M j, Y') : 'Not set' }}</span>
        </div>
      </div>
      <div class="grid-row">
        <div class="grid-cell">
          <span class="field-label">Start Time:</span>
          <span class="field-value">
            @if($checkout->start_time)
              @php
                try {
                  $time = \Carbon\Carbon::createFromFormat('H:i:s', $checkout->start_time)->format('g:i A');
                  echo $time;
                } catch (\Exception $e) {
                  echo $checkout->start_time;
                }
              @endphp
            @else
              Not set
            @endif
          </span>
        </div>
      </div>
      <div class="grid-row">
        <div class="grid-cell">
          <span class="field-label">Pickup Time:</span>
          <span class="field-value">
            @if($checkout->pickup_time)
              @php
                try {
                  $time = \Carbon\Carbon::createFromFormat('H:i:s', $checkout->pickup_time)->format('g:i A');
                  echo $time;
                } catch (\Exception $e) {
                  echo $checkout->pickup_time;
                }
              @endphp
            @else
              Not set
            @endif
          </span>
        </div>
      </div>
      @if($checkout->notes)
      <div class="grid-row">
        <div class="grid-cell">
          <span class="field-label">Notes:</span>
          <span class="field-value">{{ $checkout->notes }}</span>
        </div>
      </div>
      @endif
    </div>
  </div>
  @endif

  <!-- 4. Final Assessment -->
  @if(isset($checkout) && $checkout && $checkout->flows && is_array($checkout->flows))
  <div class="section">
    <h2>4. Final Assessment</h2>
    @if(isset($checkout->flows['rating']))
    <div class="field">
      <span class="field-label">Rating:</span>
      <span class="field-value">
        @if($checkout->flows['rating'] === 'green')
          Green (no issues)
        @elseif($checkout->flows['rating'] === 'yellow')
          Yellow (mild reaction to boarding)
          @if(isset($checkout->flows['rating_yellow_detail']))
          <div class="notes" style="margin-top: 5px;">{{ $checkout->flows['rating_yellow_detail'] }}</div>
          @endif
        @elseif($checkout->flows['rating'] === 'purple')
          Purple (reacts to boarding)
          @if(isset($checkout->flows['rating_purple_detail']))
          <div class="notes" style="margin-top: 5px;">{{ $checkout->flows['rating_purple_detail'] }}</div>
          @endif
        @endif
      </span>
    </div>
    @endif

    @if(isset($checkout->flows['pictures']) && is_array($checkout->flows['pictures']) && count($checkout->flows['pictures']) > 0)
    <div class="field">
      <div class="field-label">Checkout Pictures:</div>
      <div style="margin-top: 10px; display: flex; flex-wrap: wrap; gap: 10px;">
        @foreach($checkout->flows['pictures'] as $picture)
          <div style="margin-bottom: 10px;">
            <img src="{{ public_path('storage/checkouts/' . $picture) }}" style="width: 150px; height: 150px; object-fit: cover; border: 1px solid #e2e8f0; border-radius: 5px;" alt="Checkout Picture">
          </div>
        @endforeach
      </div>
    </div>
    @endif
  </div>
  @endif

</body>
</html>
