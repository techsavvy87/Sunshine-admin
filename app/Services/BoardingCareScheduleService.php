<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\BoardingCareTask;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class BoardingCareScheduleService
{
    public function regenerate(Appointment $appointment, array $careFlows, ?string $effectiveDate = null): array
    {
        $start = Carbon::parse($appointment->date)->startOfDay();
        $end = Carbon::parse($appointment->end_date ?: $appointment->date)->startOfDay();
        $effective = ($effectiveDate ? Carbon::parse($effectiveDate) : $start)->startOfDay()->max($start)->min($end);
        $pets = $appointment->family_pets;
        if ($pets->isEmpty() && $appointment->pet) $pets = collect([$appointment->pet]);

        $result = ['dates' => 0, 'created' => 0, 'replaced' => 0, 'preserved_completed' => 0];
        foreach (CarbonPeriod::create($effective, $end) as $day) {
            $existing = BoardingCareTask::where('appointment_id', $appointment->id)
                ->whereDate('task_date', $day)->get();
            $result['preserved_completed'] += $existing->where('status', 'completed')->count();
            $result['replaced'] += $existing->where('status', '!=', 'completed')->count();
            BoardingCareTask::where('appointment_id', $appointment->id)
                ->whereDate('task_date', $day)->where('status', '!=', 'completed')->delete();

            foreach ($pets as $pet) {
                $flows = $this->flowsForPet($careFlows, (int) $pet->id);
                $tasks = array_merge($this->feedingTasks($appointment, $pet, $day, $flows), $this->medicationTasks($appointment, $pet, $day, $flows));
                foreach ($tasks as $task) {
                    if (BoardingCareTask::where('appointment_id', $appointment->id)->whereDate('task_date', $day)->where('task_key', $task['task_key'])->exists()) continue;
                    BoardingCareTask::create($task);
                    $result['created']++;
                }
            }
            $result['dates']++;
        }
        return $result;
    }

    public function syncCompletedStatuses(Appointment $appointment, string $date, array $flows): void
    {
        $mapping = ['feeding_am' => ['feeding','AM'], 'feeding_pm' => ['feeding','PM'], 'lunch_tlr' => ['feeding','Lunch'], 'meds_dispense_am' => ['medication','AM'], 'meds_dispense_pm' => ['medication','PM']];
        foreach ($mapping as $step => [$type, $slot]) {
            if (empty($flows[$step]['process_time'])) continue;
            $query = BoardingCareTask::where('appointment_id', $appointment->id)->whereDate('task_date', $date)->where('type', $type)->where('slot', $slot)->where('status', '!=', 'completed');
            $ids = collect($flows[$step]['selected_pet_ids'] ?? [])->map(fn ($id) => (int) $id)->filter();
            if ($ids->isNotEmpty() && !$ids->contains((int) $appointment->id)) $query->whereIn('pet_id', $ids);
            $query->update(['status' => 'completed', 'completed_at' => Carbon::parse($date.' '.$flows[$step]['process_time'])]);
        }
    }

    private function flowsForPet(array $flows, int $petId): array { $p=$flows['pet_specific']??[]; $v=$p[(string)$petId]??($p[$petId]??[]); return is_array($v)?array_merge($flows,$v):$flows; }
    private function items(array $flows,string $list,string $legacy): array { $v=is_array($flows[$list]??null)?array_values(array_filter($flows[$list],'is_array')):[]; return !$v&&is_array($flows[$legacy]??null)&&array_filter($flows[$legacy])?[$flows[$legacy]]:$v; }
    private function feedingTasks($a,$p,Carbon $d,array $f): array { $out=[]; foreach(array_merge($this->items($f,'dry_food_list','dry_food'),$this->items($f,'wet_food_list','wet_food')) as $i=>$food) foreach(['am'=>'AM','lunch'=>'Lunch','pm'=>'PM'] as $k=>$slot) if($this->truthy($food['dispense_'.$k]??false)) $out[]=$this->task($a,$p,$d,'feeding',$slot,$i,$food['brand']??'Food',$food['amount']??'',null); return $out; }
    private function medicationTasks($a,$p,Carbon $d,array $f): array { $out=[]; foreach($this->items($f,'meds_list','meds') as $i=>$med) foreach(['am'=>'AM','pm'=>'PM','rest'=>'Rest','before_bed'=>'Before Bed','prn'=>'PRN','custom_time'=>'Custom Time'] as $k=>$label) if($this->truthy($med['dispense_'.$k]??false)){ $slot=$k==='custom_time'&&!empty($med['custom_time'])?$med['custom_time']:$label; $out[]=$this->task($a,$p,$d,'medication',$slot,$i,$med['name']??'Medication',$med['amount']??'',$med['meal_condition']??($med['condition']??null)); } return $out; }
    private function task($a,$p,Carbon $d,string $type,string $slot,int $index,$name,$instructions,$condition): array { return ['appointment_id'=>$a->id,'pet_id'=>$p->id,'task_date'=>$d->toDateString(),'task_key'=>sha1(implode('|',[$a->id,$p->id,$d->toDateString(),$type,$slot,$index])),'type'=>$type,'slot'=>$slot,'pet_name'=>$p->name??'Pet','name'=>trim((string)$name)?:ucfirst($type),'instructions'=>trim((string)$instructions),'meal_condition'=>$condition,'status'=>'pending']; }
    private function truthy($v): bool { return in_array($v,[true,1,'1','true'],true); }
}
