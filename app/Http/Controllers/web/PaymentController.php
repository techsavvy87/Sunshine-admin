<?php

namespace App\Http\Controllers\web;

use App\Models\PaymentLink;
use App\Models\Invoice;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\InvoicePaymentService;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class PaymentController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret_key'));
    }

    public function showPaymentPage($token)
    {
        $paymentLink = validatePaymentToken($token);

        if (!$paymentLink) {
            return view('payment.error', ['message' => 'Payment link is invalid, expired, or has already been completed.']);
        }

        $paymentLink->loadMissing('invoice.items', 'appointment.service', 'appointment.customer', 'appointment.pet');

        $invoice = $paymentLink->invoice;
        $appointment = $paymentLink->appointment;

        if (!$invoice || !$appointment) {
            return view('payment.error', ['message' => 'Invoice or appointment not found.']);
        }

        $paymentSummary = app(InvoicePaymentService::class)->buildSummary($invoice);

        $stripePublicKey = config('services.stripe.public_key');

        return view('payment.page', [
            'paymentLink' => $paymentLink,
            'invoice' => $invoice,
            'appointment' => $appointment,
            'paymentSummary' => $paymentSummary,
            'stripePublicKey' => $stripePublicKey,
            'clientSecret' => null,
        ]);
    }

    public function createPaymentIntent(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'amount' => 'nullable|numeric|min:0.5',
        ]);

        $paymentLink = validatePaymentToken($request->token);

        if (!$paymentLink) {
            return response()->json([
                'status' => false,
                'message' => 'Payment link is invalid or expired.',
            ], 400);
        }

        $invoice = $paymentLink->invoice;
        if (!$invoice) {
            return response()->json([
                'status' => false,
                'message' => 'Invoice not found for this payment link.',
            ], 404);
        }

        $paymentService = app(InvoicePaymentService::class);
        $summary = $paymentService->buildSummary($invoice);
        $balanceDue = round(floatval($summary['balance_due'] ?? 0), 2);

        if ($balanceDue <= 0) {
            return response()->json([
                'status' => false,
                'message' => 'This invoice has already been paid in full.',
            ], 422);
        }

        $requestedAmount = $request->filled('amount')
            ? round(floatval($request->amount), 2)
            : $balanceDue;

        if ($requestedAmount <= 0 || $requestedAmount > $balanceDue) {
            return response()->json([
                'status' => false,
                'message' => 'Payment amount must be greater than zero and cannot exceed the current balance due.',
            ], 422);
        }

        try {
            $intent = PaymentIntent::create([
                'amount' => intval(round($requestedAmount * 100)),
                'currency' => $paymentLink->currency,
                'payment_method_types' => ['card'],
                'metadata' => [
                    'payment_link_id' => $paymentLink->id,
                    'invoice_id' => $paymentLink->invoice_id,
                    'appointment_id' => $paymentLink->appointment_id,
                    'payment_amount' => number_format($requestedAmount, 2, '.', ''),
                ],
            ]);

            $paymentLink->update([
                'stripe_payment_intent_id' => $intent->id,
                'amount' => $requestedAmount,
                'status' => 'processing',
            ]);

            return response()->json([
                'status' => true,
                'clientSecret' => $intent->client_secret,
                'amount' => $requestedAmount,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create payment intent: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function confirmPayment(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'paymentIntentId' => 'required|string',
        ]);

        $paymentLink = validatePaymentToken($request->token);

        if (!$paymentLink) {
            return response()->json([
                'status' => false,
                'message' => 'Payment link is invalid or expired.',
            ], 400);
        }

        try {
            $intent = PaymentIntent::retrieve($request->paymentIntentId);

            if ($intent->status === 'succeeded') {
                $paymentLink->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'stripe_transaction_id' => $intent->id,
                    'payment_method' => 'card',
                ]);

                $summary = $this->updateInvoiceAfterPayment($paymentLink, $intent);

                return response()->json([
                    'status' => true,
                    'message' => 'Payment successful!',
                    'payment_amount' => round(((float) ($intent->amount_received ?? 0)) / 100, 2),
                    'payment_summary' => $summary,
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Payment was not completed successfully.',
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to confirm payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function updateInvoiceAfterPayment($paymentLink, $paymentIntent): array
    {
        $invoice = $paymentLink->invoice;
        $appointment = $paymentLink->appointment;

        if (!$invoice || !$appointment) {
            return [];
        }

        $paymentService = app(InvoicePaymentService::class);
        $result = $paymentService->recordPayment($invoice, [
            'appointment_id' => $appointment->id,
            'user_id' => $appointment->customer_id,
            'tran_date' => now(),
            'amount' => round(((float) ($paymentIntent->amount_received ?? 0)) / 100, 2),
            'payment_method' => 'card',
            'stripe_transaction_id' => $paymentIntent->id,
            'notes' => 'Stripe payment: ' . $paymentIntent->id,
        ]);

        if ($result['created']) {
            $paymentService->createAdminPaymentNotifications($invoice->fresh(), $result['transaction'], $result['summary']);
        }

        $summary = $result['summary'];
        $statusLabel = $summary['status'] === 'paid' ? 'Paid' : 'Partially Paid';
        appointment_audit_log($appointment->id, "Payment received via Stripe for invoice #{$invoice->invoice_number}. Amount: \$" . number_format($result['transaction']->amount ?? 0, 2) . ". Status: {$statusLabel}.");

        return $summary;
    }
}
