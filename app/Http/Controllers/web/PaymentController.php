<?php

namespace App\Http\Controllers\web;

use App\Models\PaymentLink;
use App\Models\Invoice;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Carbon\Carbon;

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

        $stripePublicKey = config('services.stripe.public_key');

        return view('payment.page', [
            'paymentLink' => $paymentLink,
            'invoice' => $invoice,
            'appointment' => $appointment,
            'stripePublicKey' => $stripePublicKey,
            'clientSecret' => null,
        ]);
    }

    public function createPaymentIntent(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $paymentLink = validatePaymentToken($request->token);

        if (!$paymentLink) {
            return response()->json([
                'status' => false,
                'message' => 'Payment link is invalid or expired.',
            ], 400);
        }

        try {
            $intent = PaymentIntent::create([
                'amount' => intval($paymentLink->amount * 100),
                'currency' => $paymentLink->currency,
                'payment_method_types' => ['card'],
                'metadata' => [
                    'payment_link_id' => $paymentLink->id,
                    'invoice_id' => $paymentLink->invoice_id,
                    'appointment_id' => $paymentLink->appointment_id,
                ],
            ]);

            $paymentLink->update([
                'stripe_payment_intent_id' => $intent->id,
                'status' => 'processing',
            ]);

            return response()->json([
                'status' => true,
                'clientSecret' => $intent->client_secret,
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

                $this->updateInvoiceAfterPayment($paymentLink);

                return response()->json([
                    'status' => true,
                    'message' => 'Payment successful!',
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

    private function updateInvoiceAfterPayment($paymentLink)
    {
        $invoice = $paymentLink->invoice;
        $appointment = $paymentLink->appointment;

        if (!$invoice || !$appointment) {
            return;
        }

        $invoice->status = 'paid';
        $invoice->paid_at = now();
        $invoice->save();

        $existingTransaction = Transaction::where('invoice_id', $invoice->id)
            ->where('payment_method', 'card')
            ->where('stripe_transaction_id', $paymentLink->stripe_transaction_id)
            ->first();

        if (!$existingTransaction) {
            Transaction::create([
                'appointment_id' => $appointment->id,
                'invoice_id' => $invoice->id,
                'user_id' => $appointment->customer_id,
                'tran_date' => now(),
                'amount' => $paymentLink->amount,
                'payment_method' => 'card',
                'stripe_transaction_id' => $paymentLink->stripe_transaction_id,
                'notes' => 'Stripe payment: ' . $paymentLink->stripe_transaction_id,
            ]);
        }

        appointment_audit_log($appointment->id, "Payment received via Stripe for invoice #{$invoice->invoice_number}. Amount: \${$paymentLink->amount}");
    }
}
