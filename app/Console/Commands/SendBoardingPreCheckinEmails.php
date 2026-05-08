<?php

namespace App\Console\Commands;

use App\Mail\AdminCustomerMessage;
use App\Models\Appointment;
use App\Models\BoardingPrecheckinLink;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendBoardingPreCheckinEmails extends Command
{
    protected $signature = 'emails:boarding-precheckin';

    protected $description = 'Send boarding pre check-in email exactly 24 hours before appointment check-in datetime';

    public function handle(): int
    {
        $timezone = 'America/New_York';
        $now = Carbon::now($timezone);
        $graceMinutes = (int) config('billing.precheckin_send_grace_minutes', 60);
        $windowStart = $now->copy()->subMinutes(max(1, $graceMinutes));

        $appointments = Appointment::with(['customer.profile', 'service.category'])
            ->whereIn('status', ['pending', 'checked_in'])
            ->whereDate('date', '<=', $now->copy()->addDay()->toDateString())
            ->whereDate('date', '>=', $now->copy()->subDay()->toDateString())
            ->whereHas('service.category', function ($query) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%boarding%']);
            })
            ->get();

        $sent = 0;

        foreach ($appointments as $appointment) {
            if (empty($appointment->date) || empty($appointment->start_time) || empty($appointment->customer?->email)) {
                continue;
            }

            $checkinAt = Carbon::parse($appointment->date . ' ' . $appointment->start_time, $timezone);
            $scheduledFor = $checkinAt->copy()->subDay();

            // Send when the command runs at/after the exact scheduled minute, with a small catch-up window
            // so occasional scheduler delays don't permanently skip the email.
            if (!$scheduledFor->betweenIncluded($windowStart, $now)) {
                continue;
            }

            $existingLink = BoardingPrecheckinLink::where('appointment_id', $appointment->id)->first();
            if ($existingLink && $existingLink->sent_at) {
                continue;
            }

            $rawToken = Str::random(64);
            $tokenHash = hash('sha256', $rawToken);

            $link = $existingLink ?: new BoardingPrecheckinLink();
            $link->appointment_id = $appointment->id;
            $link->token_hash = $tokenHash;
            $link->scheduled_for = $scheduledFor->copy()->setTimezone(config('app.timezone'));
            $link->sent_at = Carbon::now(config('app.timezone'));
            $link->expires_at = $checkinAt->copy()->setTimezone(config('app.timezone'));
            $link->save();

            $precheckinBaseUrl = rtrim((string) config('app.precheckin_url', config('app.url')), '/');
            $precheckinPath = route('pre-checkin.show', ['token' => $rawToken], false);
            $url = $precheckinBaseUrl . $precheckinPath;
            $customerName = trim((string) (($appointment->customer?->profile?->first_name ?? '') . ' ' . ($appointment->customer?->profile?->last_name ?? '')));
            if ($customerName === '') {
                $customerName = $appointment->customer?->name ?? 'Customer';
            }

            $checkinAtLabel = $checkinAt->format('F j, Y g:i A');
            $message = "Hi {$customerName},\n\n";
            $message .= "Your pet's boarding pre check-in is ready.\n";
            $message .= "Please complete it before your drop-off time ({$checkinAtLabel}).\n\n";
            $message .= "Secure pre check-in link:\n{$url}\n\n";
            $message .= "Thank you.";

            try {
                Mail::to($appointment->customer->email)->send(new AdminCustomerMessage([
                    'subject' => 'Boarding Pre Check-In',
                    'customer_name' => $customerName,
                    'message' => $message,
                    'sender_name' => 'Sunshine Spot Team',
                ]));
                $sent++;
                $this->info('Pre check-in email sent for appointment #' . $appointment->id);
            } catch (\Throwable $exception) {
                Log::error('Failed to send boarding pre check-in email.', [
                    'appointment_id' => $appointment->id,
                    'email' => $appointment->customer->email,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->info('Total pre check-in emails sent: ' . $sent);

        return self::SUCCESS;
    }
}
