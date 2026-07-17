<?php

namespace App\Http\Controllers\web;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payout;
use App\Models\PaymentLink;
use App\Services\InvoicePaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Stripe\Stripe;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        Stripe::setApiKey(config('services.stripe.secret_key'));
        $endpointSecret = config('services.stripe.webhook_secret');

        $sig = $request->header('stripe-signature');
        $payload = $request->getContent();

        try {
            $event = Webhook::constructEvent($payload, $sig, $endpointSecret);
        } catch (\UnexpectedValueException $e) {
            Log::error('Invalid Stripe webhook payload', ['error' => $e->getMessage()]);
            return response('Invalid payload', 400);
        } catch (SignatureVerificationException $e) {
            Log::error('Invalid Stripe webhook signature', ['error' => $e->getMessage()]);
            return response('Invalid signature', 400);
        }

        switch ($event->type) {
            case 'payment_intent.succeeded':
                $this->handlePaymentIntentSucceeded($event->data->object);
                break;

            case 'payment_intent.payment_failed':
                $this->handlePaymentIntentFailed($event->data->object);
                break;

            case 'payout.created':
            case 'payout.updated':
            case 'payout.paid':
            case 'payout.failed':
            case 'payout.canceled':
            case 'payout.reconciliation_completed':
                $this->handlePayoutUpdated($event->data->object);
                break;

            default:
                Log::info('Unhandled Stripe event type', ['type' => $event->type]);
        }

        return response('OK', 200);
    }

    private function handlePaymentIntentSucceeded($paymentIntent)
    {
        $paymentLink = PaymentLink::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if (!$paymentLink) {
            Log::warning('Payment intent not found in database, attempting metadata fallback', ['intent_id' => $paymentIntent->id]);
            // Try to locate invoice via metadata (when Checkout Sessions were used)
            $invoiceId = isset($paymentIntent->metadata) && isset($paymentIntent->metadata->invoice_id)
                ? $paymentIntent->metadata->invoice_id
                : (is_array($paymentIntent->metadata) && isset($paymentIntent->metadata['invoice_id']) ? $paymentIntent->metadata['invoice_id'] : null);

            if ($invoiceId) {
                $invoice = Invoice::find($invoiceId);
                if ($invoice) {
                    $appointment = $invoice->appointment;

                    $paymentService = app(InvoicePaymentService::class);
                    $result = $paymentService->recordPayment($invoice, [
                        'appointment_id' => $appointment?->id,
                        'user_id' => $appointment?->customer_id ?? $invoice->customer_id,
                        'tran_date' => now(),
                        'amount' => round(((float) ($paymentIntent->amount_received ?? 0)) / 100, 2),
                        'payment_method' => 'card',
                        'stripe_transaction_id' => $paymentIntent->id,
                        'notes' => 'Stripe webhook payment (metadata): ' . $paymentIntent->id,
                    ]);

                    if ($result['created']) {
                        $paymentService->createAdminPaymentNotifications($invoice->fresh(), $result['transaction'], $result['summary']);
                    }

                    appointment_audit_log($appointment?->id, "Stripe webhook: Payment confirmed for invoice #{$invoice->invoice_number}. Amount: \$" . number_format($result['transaction']->amount ?? 0, 2) . '.');

                    Log::info('Payment processed via webhook using metadata fallback', [
                        'invoice_id' => $invoice->id,
                        'intent_id' => $paymentIntent->id,
                    ]);
                    return;
                }
            }

            // If we couldn't find a related invoice, stop processing
            return;
        }

        if ($paymentLink->status === 'completed') {
            Log::info('Payment already processed, skipping duplicate', ['payment_link_id' => $paymentLink->id]);
            return;
        }

        $invoice = $paymentLink->invoice;
        $appointment = $paymentLink->appointment;

        if (!$invoice || !$appointment) {
            Log::error('Invoice or appointment not found', ['payment_link_id' => $paymentLink->id]);
            return;
        }

        $paymentService = app(InvoicePaymentService::class);
        $result = $paymentService->recordPayment($invoice, [
            'appointment_id' => $appointment->id,
            'user_id' => $appointment->customer_id,
            'tran_date' => now(),
            'amount' => round(((float) ($paymentIntent->amount_received ?? 0)) / 100, 2),
            'payment_method' => 'card',
            'stripe_transaction_id' => $paymentIntent->id,
            'notes' => 'Stripe webhook payment: ' . $paymentIntent->id,
        ]);

        $paymentLink->update([
            'status' => 'completed',
            'completed_at' => now(),
            'stripe_transaction_id' => $paymentIntent->id,
            'payment_method' => 'card',
        ]);

        if ($result['created']) {
            $paymentService->createAdminPaymentNotifications($invoice->fresh(), $result['transaction'], $result['summary']);
        }

        appointment_audit_log($appointment->id, "Stripe webhook: Payment confirmed for invoice #{$invoice->invoice_number}. Amount: \$" . number_format($result['transaction']->amount ?? 0, 2) . '.');

        Log::info('Payment successfully processed via webhook', [
            'payment_link_id' => $paymentLink->id,
            'invoice_id' => $invoice->id,
        ]);
    }

    private function handlePaymentIntentFailed($paymentIntent)
    {
        $paymentLink = PaymentLink::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if (!$paymentLink) {
            Log::warning('Payment intent not found in database', ['intent_id' => $paymentIntent->id]);
            return;
        }

        $errorMessage = $paymentIntent->last_payment_error ? $paymentIntent->last_payment_error->message : 'Unknown error';

        $paymentLink->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);

        $appointment = $paymentLink->appointment;
        if ($appointment) {
            appointment_audit_log($appointment->id, "Stripe payment failed for invoice #{$paymentLink->invoice->invoice_number}. Error: {$errorMessage}");
        }

        Log::warning('Payment failed via webhook', [
            'payment_link_id' => $paymentLink->id,
            'error' => $errorMessage,
        ]);
    }

    private function handlePayoutUpdated($stripePayout): void
    {
        $payout = Payout::query()
            ->where('stripe_payout_id', $stripePayout->id)
            ->first();

        if (!$payout) {
            Log::warning('Stripe payout not found in database', ['payout_id' => $stripePayout->id]);
            return;
        }

        $payout->update([
            'status' => (string) ($stripePayout->status ?? $payout->status),
            'arrival_date' => !empty($stripePayout->arrival_date)
                ? Carbon::createFromTimestamp($stripePayout->arrival_date)
                : $payout->arrival_date,
        ]);

        Log::info('Stripe payout updated via webhook', [
            'payout_id' => $payout->id,
            'stripe_payout_id' => $stripePayout->id,
            'status' => $payout->status,
        ]);
    }
}
