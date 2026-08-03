<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boarding_care_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pet_id')->nullable()->constrained('pet_profiles')->nullOnDelete();
            $table->date('task_date');
            $table->string('task_key', 40);
            $table->string('type', 20);
            $table->string('slot', 50);
            $table->string('pet_name')->nullable();
            $table->string('name')->nullable();
            $table->text('instructions')->nullable();
            $table->string('meal_condition')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['appointment_id', 'task_date', 'task_key']);
            $table->index(['appointment_id', 'task_date', 'status']);
        });

        // Move schedules created by the earlier JSON implementation out of
        // process logs. Care-only process rows were never staff-created logs.
        DB::table('processes')->orderBy('id')->each(function ($process) {
            $flows = json_decode($process->flows ?? '', true);
            if (!is_array($flows) || !array_key_exists('care_tasks', $flows)) {
                return;
            }

            foreach ((array) $flows['care_tasks'] as $task) {
                if (!is_array($task)) continue;
                DB::table('boarding_care_tasks')->insertOrIgnore([
                    'appointment_id' => $process->appointment_id,
                    'pet_id' => $task['pet_id'] ?? null,
                    'task_date' => $task['date'] ?? $process->date,
                    'task_key' => $task['id'] ?? sha1(json_encode($task)),
                    'type' => $task['type'] ?? 'feeding',
                    'slot' => $task['slot'] ?? '',
                    'pet_name' => $task['pet_name'] ?? null,
                    'name' => $task['name'] ?? null,
                    'instructions' => $task['instructions'] ?? null,
                    'meal_condition' => $task['meal_condition'] ?? null,
                    'status' => $task['status'] ?? 'pending',
                    'completed_at' => $task['completed_at'] ?? null,
                    'created_at' => $process->created_at,
                    'updated_at' => $process->updated_at,
                ]);
            }

            unset($flows['care_tasks'], $flows['care_plan_effective_date'], $flows['care_tasks_generated_at']);
            if (empty($flows)) {
                DB::table('processes')->where('id', $process->id)->delete();
            } else {
                DB::table('processes')->where('id', $process->id)->update(['flows' => json_encode($flows)]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boarding_care_tasks');
    }
};
