@php
  $flows = $process->flows ?? [];
  if (!is_array($flows)) {
    $flows = [];
  }
  $appointmentId = $process->appointment_id ?? null;
  $aidStr = (string) $appointmentId;
  $staffNames = $staff_names ?? [];
  if (!is_array($staffNames)) {
    $staffNames = [];
  }

  $processEmployeeName = '';
  if ($process->relationLoaded('staff') && $process->staff) {
    $p = $process->staff->profile;
    $processEmployeeName = $p ? trim(($p->first_name ?? '') . ' ' . ($p->last_name ?? '')) : $process->staff->name ?? '';
  }
@endphp
@php
  $getEmployeeForStep = function($stepData) use ($staffNames, $processEmployeeName) {
    if (!is_array($stepData) || empty($stepData['staff_sign_off']) || !is_array($stepData['staff_sign_off'])) {
      return $processEmployeeName ?: '—';
    }
    $names = [];
    foreach ($stepData['staff_sign_off'] as $uid) {
      if ($uid === null || $uid === '') continue;
      $key = (string) (is_numeric($uid) ? (int) $uid : $uid);
      $names[] = $staffNames[$key] ?? $staffNames[(int) $uid] ?? null;
    }
    $names = array_filter($names);
    return count($names) > 0 ? implode(', ', $names) : ($processEmployeeName ?: '—');
  };

  $formatTime = function($t) {
    if (!$t || trim((string)$t) === '') return '—';
    $t = trim((string)$t);
    try {
      $dt = \Carbon\Carbon::parse($t);
      return $dt->format('g:i A');
    } catch (\Exception $e) {
      return $t;
    }
  };

  $formatDetailIfDatetime = function($val) {
    if ($val === null || $val === '') return $val;
    $s = trim((string)$val);
    if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $s)) {
      try {
        return \Carbon\Carbon::parse($s)->format('M j, Y g:i A');
      } catch (\Exception $e) {}
    }
    return $val;
  };

  $legacyTimeKeys = [
    'food_prep_am' => 'am_meal_prep_time',
    'meds_prep_am' => 'am_med_prep_time',
    'feeding_am' => 'am_meal_dispense_time',
    'meds_dispense_am' => 'am_med_dispense_time',
    'reports_am' => null,
    'check_pet' => 'nose_tail_time',
    'treatment_plan' => null,
    'treatment_list' => null,
    'treatment_list_tlr' => null,
    'treatments_tlr' => null,
    'next_day_treatment_list_tlr' => null,
    'lunch_tlr' => 'rest_1200_time',
    'rest_tlr' => null,
    'food_prep_pm' => 'pm_meal_prep_time',
    'meds_prep_pm' => 'pm_med_prep_time',
    'feeding_pm' => 'pm_meal_dispense_time',
    'meds_dispense_pm' => 'pm_med_dispense_time',
    'reports_pm' => null,
  ];

  $getStepRow = function($stepId, $taskLabel) use ($flows, $formatTime, $formatDetailIfDatetime, $legacyTimeKeys, $getEmployeeForStep, $appointmentId, $aidStr) {
    $stepData = $flows[$stepId] ?? null;
    $time = '—';
    $employee = is_array($stepData) ? $getEmployeeForStep($stepData) : '—';
    $detail = '';

    if (is_array($stepData)) {
      $t = $stepData['process_time'] ?? $stepData['processTime'] ?? null;
      if ($t) {
        $time = $formatTime($t);
      }
    }
    if ($time === '—' && !empty($legacyTimeKeys[$stepId]) && !empty($flows[$legacyTimeKeys[$stepId]])) {
      $time = $formatTime($flows[$legacyTimeKeys[$stepId]]);
    }

    if (is_array($stepData)) {
      if ($stepId === 'reports_am' || $stepId === 'reports_pm') {
        $issues = $stepData['issues'] ?? [];
        $statuses = $stepData['statuses'] ?? [];
        $val = $issues[$aidStr] ?? ($appointmentId !== null ? ($issues[$appointmentId] ?? '') : '');
        $status = strtolower(trim((string) ($statuses[$aidStr] ?? ($appointmentId !== null ? ($statuses[$appointmentId] ?? '') : ''))));
        if (($val === null || trim((string) $val) === '') && $status === 'partial_meal') {
          $feedingKey = $stepId === 'reports_am' ? 'feeding_am' : 'feeding_pm';
          $partialMealNotes = data_get($flows, $feedingKey . '.partial_meal_notes', []);
          $val = $partialMealNotes[$aidStr] ?? ($appointmentId !== null ? ($partialMealNotes[$appointmentId] ?? '') : '');
        }
        if ($val !== null && trim((string)$val) !== '') {
          $detail = trim((string)$val);
        }
      } elseif ($stepId === 'check_pet') {
        $checkData = $stepData['check_data'][$aidStr] ?? ($appointmentId !== null ? ($stepData['check_data'][$appointmentId] ?? []) : []);
        if (is_array($checkData)) {
          $parts = [];
          foreach (['nose', 'ears', 'eyes', 'mouth', 'body_coat', 'paws_feet', 'abdomen', 'digestive', 'diarrhea'] as $k) {
            $s = $checkData[$k]['status'] ?? $checkData[$k] ?? '';
            if ($s && $s !== 'okay') {
              $parts[] = ucfirst(str_replace('_', '/', $k));
            }
          }
          $detail = count($parts) ? implode(', ', $parts) : 'Okay';
        }
      } elseif ($stepId === 'treatment_plan') {
        $td = $stepData['treatment_data'][$aidStr] ?? ($appointmentId !== null ? ($stepData['treatment_data'][$appointmentId] ?? []) : []);
        if (is_array($td)) {
          $opt = $td['option'] ?? '';
          $optLabel = $opt === 'in-house' ? 'In-house' : ($opt === 'vet-watch' ? 'Vet watch' : $opt);
          $det = trim($td['detail'] ?? '');
          if ($optLabel && $det) {
            $detail = 'Option: ' . $optLabel . ', Detail: ' . $det;
          } elseif ($optLabel) {
            $detail = $optLabel;
          } else {
            $detail = $det;
          }
        }
      } elseif ($stepId === 'treatment_list') {
        $completed = $stepData['completed_treatments'][$aidStr] ?? ($appointmentId !== null ? ($stepData['completed_treatments'][$appointmentId] ?? null) : null);
        if ($completed !== null) {
          $detail = ($completed === true || $completed === 'true') ? 'Completed' : 'Not completed';
        }
      } elseif ($stepId === 'treatments_tlr') {
        $res = $stepData['results'][$aidStr] ?? ($appointmentId !== null ? ($stepData['results'][$appointmentId] ?? []) : []);
        if (is_array($res)) {
          $r = strtolower($res['result'] ?? '');
          $resultLabel = $r === 'continue' ? 'Continue' : ($r === 'resolved' ? 'Resolved' : ($r === 'escalate' ? 'Escalate' : $r));
          $d = trim($res['detail'] ?? '');
          if ($resultLabel && $d) {
            $detail = 'Result: ' . $resultLabel . ', Detail: ' . $d;
          } elseif ($resultLabel) {
            $detail = $resultLabel;
          } else {
            $detail = $d;
          }
        }
      } elseif ($stepId === 'treatment_list_tlr') {
        $reported = $stepData['reported'][$aidStr] ?? ($appointmentId !== null ? ($stepData['reported'][$appointmentId] ?? '') : '');
        if ($reported !== null && $reported !== '') {
          $detail = (string) $reported;
        }
      } elseif ($stepId === 'next_day_treatment_list_tlr') {
        $vetVisit = $stepData['vet_visit'][$aidStr] ?? ($appointmentId !== null ? ($stepData['vet_visit'][$appointmentId] ?? null) : null);
        if ($vetVisit !== null) {
          $detail = ($vetVisit === true || $vetVisit === 'true') ? 'Yes' : 'No';
        }
      }
    }

    return [
      'task' => $taskLabel,
      'time' => $time,
      'employee' => $employee,
      'detail' => $formatDetailIfDatetime($detail),
    ];
  };

  $sections = [
    'AM Feeding/Meds' => [
      ['food_prep_am', 'Food Prep (AM)'],
      ['meds_prep_am', 'Meds Prep (AM)'],
      ['feeding_am', 'Feeding Dispense (AM)'],
      ['meds_dispense_am', 'Meds Dispense (AM)'],
      ['reports_am', 'Reports (AM)'],
    ],
    'Nose to Tail' => [
      ['check_pet', 'Check Pet'],
      ['treatment_plan', 'Treatment Plan'],
      ['treatment_list', 'Treatment List'],
    ],
    'Treatment/Lunch/Rest' => [
      ['treatment_list_tlr', 'Treatment List'],
      ['treatments_tlr', 'Treatments'],
      ['next_day_treatment_list_tlr', "Next Day's Treatment List"],
      ['lunch_tlr', 'Lunch'],
      ['rest_tlr', 'Rest'],
    ],
    'PM Feeding/Meds' => [
      ['food_prep_pm', 'Food Prep (PM)'],
      ['meds_prep_pm', 'Meds Prep (PM)'],
      ['feeding_pm', 'Feeding Dispense (PM)'],
      ['meds_dispense_pm', 'Meds Dispense (PM)'],
      ['reports_pm', 'Reports (PM)'],
    ],
  ];
@endphp

<div class="space-y-4">
  @foreach($sections as $sectionTitle => $steps)
  <div class="border border-base-300 rounded-box overflow-hidden">
    <p class="font-medium p-3 pb-0">{{ $sectionTitle }}</p>
    <table class="table table-sm table-fixed w-full">
      <thead>
        <tr>
          <th class="text-start w-auto">Task</th>
          <th class="text-center w-24">Time</th>
          <th class="text-center w-28">Employee</th>
          <th class="text-start w-56 min-w-48">Details</th>
        </tr>
      </thead>
      <tbody>
        @foreach($steps as $step)
          @php $row = $getStepRow($step[0], $step[1]); @endphp
          <tr>
            <td class="text-sm">{{ $row['task'] }}</td>
            <td class="text-sm text-center w-24">{{ $row['time'] }}</td>
            <td class="text-sm text-center w-28">{{ $row['employee'] ?: '—' }}</td>
            <td class="text-sm w-56 min-w-48">{{ $row['detail'] ?: '—' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @endforeach
</div>
