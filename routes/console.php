<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Carbon\Carbon;
use App\Models\TimeSlot;
use App\Models\Service;
use App\Console\Commands\SendGroupClassReminders;
use App\Console\Commands\MonitorPetVaccineExpirations;
use App\Console\Commands\SendBoardingBookingReminders;
use App\Console\Commands\SendBoardingPreCheckinEmails;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule::call(function () {
//     // create time slots records that will be moved to scheduled task
//     $today = Carbon::today()->toDateString();

//     $services = Service::where('status', 'active')->where('level', 'primary')->get();
//     $isTimeSlots = TimeSlot::whereDate('date', $today)->exists();
//     if (!$isTimeSlots) {
//         foreach ($services as $service) {
//             $duration = $service->duration; // Duration in minutes
//             $startTime = Carbon::createFromTime(9, 0); // 9:00 AM
//             $endTime = Carbon::createFromTime(17, 0); // 5:00 PM

//             while ($startTime->addHours($duration)->lessThanOrEqualTo($endTime)) {
//                 TimeSlot::create([
//                     'service_id' => $service->id,
//                     'staff_id' => null,
//                     'date' => $today,
//                     'start_time' => $startTime->copy()->subHours($duration)->toTimeString(),
//                     'end_time' => $startTime->toTimeString(),
//                     'status' => 'available',
//                 ]);
//             }
//         }
//     }
// })->daily();

Schedule::command(SendGroupClassReminders::class)
    ->timezone('America/New_York')
    ->dailyAt('08:00')
    ->withoutOverlapping();
Schedule::command(SendBoardingBookingReminders::class)
    ->timezone('America/New_York')
    ->dailyAt('11:00')
    ->withoutOverlapping();
Schedule::command(SendBoardingPreCheckinEmails::class)
    ->timezone('America/New_York')
    ->everyMinute()
    ->withoutOverlapping();
Schedule::command(MonitorPetVaccineExpirations::class)
    ->timezone('America/New_York')
    ->dailyAt('01:00')
    ->withoutOverlapping();