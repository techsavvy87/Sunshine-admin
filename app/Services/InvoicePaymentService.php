<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;

class InvoicePaymentService
{
    public function buildSummary(Invoice $invoice): array
    {
        $invoice->loadMissing(['items', 'appointment.service', 'appointment.customer.profile', 'transactions']);

        $appointment = $invoice->appointment;
        $lineSubtotal = round((float) $invoice->items->sum(function ($item) {
            return floatval($item->price ?? 0);
        }), 2);
        $discountAmount = round(floatval($invoice->discount_amount ?? 0), 2);
        $subtotalAmount = max(0, round($lineSubtotal - $discountAmount, 2));
        $stateTaxRate = ($appointment && isBoardingService($appointment->service))
            ? floatval(config('billing.state_tax_rate', 7))
            : 0;
        $stateTaxAmount = round($subtotalAmount * ($stateTaxRate / 100), 2);
        $totalAmount = round($subtotalAmount + $stateTaxAmount, 2);

        $onlinePayment = 0.0;
        $cashPayment = 0.0;
        $checkPayment = 0.0;
        $otherPayment = 0.0;
        $latestTransactionDate = null;

        foreach ($invoice->transactions as $transaction) {
            $amount = round(floatval($transaction->amount ?? 0), 2);
            $method = $this->normalizePaymentMethod($transaction);

            if ($method === 'cash') {
                $cashPayment += $amount;
            } elseif ($method === 'check') {
                $checkPayment += $amount;
            } elseif (in_array($method, ['card', 'stripe'], true)) {
                $onlinePayment += $amount;
            } else {
                $otherPayment += $amount;
            }

            if ($transaction->tran_date && (!$latestTransactionDate || $transaction->tran_date->gt($latestTransactionDate))) {
                $latestTransactionDate = $transaction->tran_date->copy();
            }
        }

        $inPersonPayment = round($cashPayment + $checkPayment, 2);
        $paymentsReceived = round($onlinePayment + $inPersonPayment + $otherPayment, 2);
        $balanceDue = max(0, round($totalAmount - $paymentsReceived, 2));
        $currentStatus = strtolower((string) ($invoice->status ?? 'draft'));

        if ($balanceDue <= 0.00001) {
            $status = 'paid';
        } elseif ($paymentsReceived > 0) {
            $status = 'partially_paid';
        } elseif (in_array($currentStatus, ['draft', 'sent', 'void', 'finalized'], true)) {
            $status = $currentStatus;
        } else {
            $status = 'draft';
        }

        return [
            'line_subtotal' => $lineSubtotal,
            'discount_amount' => $discountAmount,
            'subtotal_amount' => $subtotalAmount,
            'state_tax_rate' => $stateTaxRate,
            'state_tax_amount' => $stateTaxAmount,
            'total_amount' => $totalAmount,
            'online_payment' => round($onlinePayment, 2),
            'cash_payment' => round($cashPayment, 2),
            'check_payment' => round($checkPayment, 2),
            'in_person_payment' => $inPersonPayment,
            'other_payment' => round($otherPayment, 2),
            'payments_received' => $paymentsReceived,
            'balance_due' => $balanceDue,
            'status' => $status,
            'paid_at' => $status === 'paid' ? ($latestTransactionDate ?: now()) : null,
            'latest_transaction_at' => $latestTransactionDate,
        ];
    }

    public function syncInvoiceState(Invoice $invoice): array
    {
        $summary = $this->buildSummary($invoice);

        $invoice->status = $summary['status'];
        $invoice->paid_at = $summary['status'] === 'paid'
            ? $summary['paid_at']
            : null;
        $invoice->save();

        return $summary;
    }

    public function recordPayment(Invoice $invoice, array $attributes): array
    {
        $invoice->loadMissing('appointment');
        $appointment = $invoice->appointment;
        $transaction = null;
        $created = false;

        $stripeTransactionId = trim((string) ($attributes['stripe_transaction_id'] ?? ''));
        $lastPaymentId = trim((string) ($attributes['last_payment_id'] ?? ''));

        if ($stripeTransactionId !== '') {
            $transaction = Transaction::where('invoice_id', $invoice->id)
                ->where('stripe_transaction_id', $stripeTransactionId)
                ->first();
        }

        if (!$transaction && $lastPaymentId !== '') {
            $transaction = Transaction::where('invoice_id', $invoice->id)
                ->where('last_payment_id', $lastPaymentId)
                ->first();
        }

        if (!$transaction) {
            $transaction = new Transaction;
            $transaction->appointment_id = $attributes['appointment_id'] ?? ($appointment->id ?? null);
            $transaction->invoice_id = $invoice->id;
            $transaction->user_id = $attributes['user_id'] ?? ($appointment->customer_id ?? $invoice->customer_id);
            $transaction->tran_date = $attributes['tran_date'] ?? Carbon::now();
            $transaction->amount = round(floatval($attributes['amount'] ?? 0), 2);
            $transaction->payment_method = $attributes['payment_method'] ?? null;
            $transaction->stripe_transaction_id = $stripeTransactionId !== '' ? $stripeTransactionId : null;
            $transaction->last_payment_id = $lastPaymentId !== '' ? $lastPaymentId : null;
            $transaction->notes = $attributes['notes'] ?? null;
            $transaction->save();
            $created = true;
        }

        $invoice->refresh();
        $summary = $this->syncInvoiceState($invoice);

        return [
            'transaction' => $transaction,
            'created' => $created,
            'summary' => $summary,
        ];
    }

    public function createAdminPaymentNotifications(Invoice $invoice, Transaction $transaction, array $summary): void
    {
        $invoice->loadMissing('appointment.customer.profile');
        $appointment = $invoice->appointment;
        if (!$appointment) {
            return;
        }

        $paymentMethod = $this->normalizePaymentMethod($transaction);
        $paymentLabel = $this->humanizePaymentMethod($paymentMethod);
        $paymentAmount = round(floatval($transaction->amount ?? 0), 2);
        $customerName = trim((($appointment->customer->profile->first_name ?? '') . ' ' . ($appointment->customer->profile->last_name ?? '')))
            ?: ($appointment->customer->name ?? 'Customer');

        if (($summary['balance_due'] ?? 0) <= 0.00001) {
            $title = $paymentMethod === 'card' ? 'Invoice Paid' : 'Payment Received';
            $message = $paymentMethod === 'card'
                ? "Appointment #{$appointment->id} has been fully paid online."
                : "Appointment #{$appointment->id} has been fully paid.";
        } else {
            $title = 'Invoice Partially Paid';
            $message = $paymentMethod === 'card'
                ? "Appointment #{$appointment->id} has an online payment of $" . number_format($paymentAmount, 2) . "."
                : "Appointment #{$appointment->id} has a {$paymentLabel} payment of $" . number_format($paymentAmount, 2) . ".";
        }

        $metadata = [
            'appointment_id' => $appointment->id,
            'invoice_id' => $invoice->id,
            'customer_name' => $customerName,
            'payment_amount' => $paymentAmount,
            'payment_type' => $paymentMethod,
            'payment_type_label' => $paymentLabel,
            'timestamp' => optional($transaction->tran_date)->format('Y-m-d H:i:s'),
            'balance_due' => $summary['balance_due'] ?? null,
            'payments_received' => $summary['payments_received'] ?? null,
            'invoice_status' => $summary['status'] ?? null,
        ];

        $staffRecipients = User::query()
            ->whereHas('roles', function ($query) {
                $query->whereRaw('LOWER(title) <> ?', ['customer']);
            })
            ->pluck('id');

        foreach ($staffRecipients as $userId) {
            $notification = new Notification;
            $notification->user_id = $userId;
            $notification->sender_id = null;
            $notification->title = $title;
            $notification->message = $message;
            $notification->type = 'invoice_payment';
            $notification->metadata = $metadata;
            $notification->is_read = false;
            $notification->save();
        }
    }

    private function normalizePaymentMethod(Transaction $transaction): string
    {
        $paymentMethod = strtolower(trim((string) ($transaction->payment_method ?? '')));

        if (in_array($paymentMethod, ['cash'], true)) {
            return 'cash';
        }

        if (in_array($paymentMethod, ['check'], true)) {
            return 'check';
        }

        if ($transaction->stripe_transaction_id || $transaction->last_payment_id || in_array($paymentMethod, ['card', 'cc', 'credit card', 'credit_card', 'stripe'], true)) {
            return 'card';
        }

        return $paymentMethod !== '' ? $paymentMethod : 'other';
    }

    private function humanizePaymentMethod(string $paymentMethod): string
    {
        return match ($paymentMethod) {
            'card' => 'online',
            'cash' => 'cash',
            'check' => 'check',
            default => str_replace('_', ' ', $paymentMethod),
        };
    }
}