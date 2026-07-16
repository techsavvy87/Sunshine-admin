<?php

namespace App\Http\Controllers\web;

use App\Http\Controllers\Controller;
use App\Models\PaymentLink;
use App\Models\Payout;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\Account as StripeAccount;
use Stripe\Payout as StripePayout;
use Stripe\Stripe;

class AdminPaymentController extends Controller
{
    public function payments(Request $request)
    {
        if (!$this->canAccessFinancials()) {
            return redirect()->route('dashboard');
        }

        $search = trim((string) $request->get('search', ''));
        $paymentType = trim((string) $request->get('payment_type', ''));
        $paymentStatus = trim((string) $request->get('payment_status', ''));
        $perPage = (int) $request->get('per_page', 20);
        $perPage = in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 20;

        $payments = DB::query()
            ->fromSub($this->buildPaymentHistoryQuery(), 'payment_history')
            ->when($paymentType !== '', function ($query) use ($paymentType) {
                $query->where('payment_type', $paymentType);
            })
            ->when($paymentStatus !== '', function ($query) use ($paymentStatus) {
                $query->where('payment_status', $paymentStatus);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $like = '%' . $search . '%';

                    $subQuery->where('invoice_reference', 'like', $like)
                        ->orWhere('appointment_reference', 'like', $like)
                        ->orWhere('customer_name', 'like', $like)
                        ->orWhere('payment_type', 'like', $like)
                        ->orWhere('payment_status', 'like', $like)
                        ->orWhere('stripe_payment_id', 'like', $like);
                });
            })
            ->orderByDesc('sort_at')
            ->orderByDesc('row_key')
            ->paginate($perPage)
            ->withQueryString();

        $payments->setCollection(
            $payments->getCollection()->map(function (object $payment) {
                $payment->amount = (float) ($payment->amount ?? 0);
                $payment->payment_date = !empty($payment->payment_date)
                    ? Carbon::parse($payment->payment_date)
                    : null;
                $payment->customer_avatar_url = !empty($payment->customer_avatar_path)
                    ? asset('storage/profiles/' . ltrim($payment->customer_avatar_path, '/'))
                    : null;
                $payment->customer_initials = $this->resolveCustomerInitials($payment->customer_name ?? '');

                return $payment;
            })
        );

        return view('financials.payments', compact(
            'payments',
            'search',
            'paymentType',
            'paymentStatus'
        ));
    }

    public function payouts(Request $request)
    {
        if (!$this->canAccessFinancials()) {
            return redirect()->route('dashboard');
        }

        $perPage = (int) $request->get('per_page', 10);
        $perPage = in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 10;

        $payouts = Payout::query()
            ->with('creator')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        [$balanceSummary, $balanceError] = $this->getLocalBalanceSummary();

        return view('financials.payouts', compact(
            'payouts',
            'balanceSummary',
            'balanceError'
        ));
    }

    public function withdraw(Request $request)
    {
        if (!canWithdrawFunds()) {
            return redirect()->route('financials.payouts')->with([
                'status' => 'fail',
                'message' => 'Only the facility owner can perform this action.',
            ]);
        }

        $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            $payoutRecord = DB::transaction(function () use ($request) {
                $availableAmount = $this->getWithdrawableAmount();
                $requestedAmount = round((float) $request->input('amount'), 2);

                if ($availableAmount <= 0) {
                    throw new \RuntimeException('No available Stripe balance to withdraw.');
                }

                if ($requestedAmount > $availableAmount) {
                    throw new \RuntimeException('Requested payout amount exceeds the available card balance.');
                }

                if (!config('services.stripe.secret_key')) {
                    throw new \RuntimeException('Stripe is not configured for payouts.');
                }

                // For local development or testing with Stripe test keys, we simulate a payout record without making an actual API call to Stripe.
                if (app()->environment('local') || str_starts_with(config('services.stripe.secret_key'), 'sk_test_')) {
                    return Payout::create([
                        'amount' => $requestedAmount,
                        'currency' => 'usd',
                        'stripe_payout_id' => 'po_test_' . uniqid(),
                        'status' => 'paid',
                        'arrival_date' => now()->addDays(2),
                        'created_by' => auth()->id(),
                    ]);
                }

                Stripe::setApiKey(config('services.stripe.secret_key'));
                $this->assertPayoutAccountReady('usd');

                $stripePayout = StripePayout::create([
                    'amount' => (int) round($requestedAmount * 100),
                    'currency' => 'usd',
                ], $this->stripeRequestOptions());

                return Payout::create([
                    'amount' => $requestedAmount,
                    'currency' => strtolower((string) ($stripePayout->currency ?? 'usd')),
                    'stripe_payout_id' => $stripePayout->id,
                    'status' => (string) ($stripePayout->status ?? 'pending'),
                    'arrival_date' => !empty($stripePayout->arrival_date)
                        ? Carbon::createFromTimestamp($stripePayout->arrival_date)
                        : null,
                    'created_by' => auth()->id(),
                ]);
            });
        } catch (\Throwable $exception) {
            return redirect()->route('financials.payouts')->with([
                'status' => 'fail',
                'message' => 'Withdrawal failed: ' . $this->formatPayoutErrorMessage($exception),
            ]);
        }

        return redirect()->route('financials.payouts')->with([
            'status' => 'success',
            'message' => 'Withdrawal requested successfully. Stripe payout ID: ' . $payoutRecord->stripe_payout_id,
        ]);
    }

    private function canAccessFinancials(): bool
    {
        return hasPermission(14, 'can_read') || hasPermission(14, 'can_create');
    }

    private function buildPaymentHistoryQuery()
    {
        $invoiceNameExpression = "NULLIF(TRIM(CONCAT_WS(' ', i.first_name, i.last_name)), '')";
        $transactionCustomerNameExpression = "COALESCE(
            {$invoiceNameExpression},
            NULLIF(i.email, ''),
            NULLIF(TRIM(CONCAT_WS(' ', tu_profile.first_name, tu_profile.last_name)), ''),
            NULLIF(tu.name, ''),
            NULLIF(tu.email, ''),
            NULLIF(TRIM(CONCAT_WS(' ', iu_profile.first_name, iu_profile.last_name)), ''),
            NULLIF(iu.name, ''),
            NULLIF(iu.email, ''),
            NULLIF(TRIM(CONCAT_WS(' ', au_profile.first_name, au_profile.last_name)), ''),
            NULLIF(au.name, ''),
            NULLIF(au.email, ''),
            'Unknown Customer'
        )";
        $paymentLinkCustomerNameExpression = "COALESCE(
            {$invoiceNameExpression},
            NULLIF(i.email, ''),
            NULLIF(TRIM(CONCAT_WS(' ', iu_profile.first_name, iu_profile.last_name)), ''),
            NULLIF(iu.name, ''),
            NULLIF(iu.email, ''),
            NULLIF(TRIM(CONCAT_WS(' ', au_profile.first_name, au_profile.last_name)), ''),
            NULLIF(au.name, ''),
            NULLIF(au.email, ''),
            'Unknown Customer'
        )";
        $legacyCustomerNameExpression = "COALESCE(
            {$invoiceNameExpression},
            NULLIF(i.email, ''),
            NULLIF(TRIM(CONCAT_WS(' ', iu_profile.first_name, iu_profile.last_name)), ''),
            NULLIF(iu.name, ''),
            NULLIF(iu.email, ''),
            'Unknown Customer'
        )";
        $invoiceItemTotals = DB::table('invoice_items')
            ->selectRaw('invoice_id, COALESCE(SUM(price), 0) as subtotal')
            ->groupBy('invoice_id');
        $stateTaxRate = (float) config('billing.state_tax_rate', 7);

        $transactions = DB::table('transactions as t')
            ->leftJoin('invoices as i', 'i.id', '=', 't.invoice_id')
            ->leftJoin('appointments as ta', 'ta.id', '=', 't.appointment_id')
            ->leftJoin('appointments as ia', 'ia.id', '=', 'i.appointment_id')
            ->leftJoin('users as tu', 'tu.id', '=', 't.user_id')
            ->leftJoin('profiles as tu_profile', 'tu_profile.user_id', '=', 'tu.id')
            ->leftJoin('users as iu', 'iu.id', '=', 'i.customer_id')
            ->leftJoin('profiles as iu_profile', 'iu_profile.user_id', '=', 'iu.id')
            ->leftJoin('users as au', 'au.id', '=', DB::raw('COALESCE(ta.customer_id, ia.customer_id)'))
            ->leftJoin('profiles as au_profile', 'au_profile.user_id', '=', 'au.id')
            ->where(function ($query) {
                $query->whereIn('t.payment_method', ['cash', 'card', 'cc'])
                    ->orWhereNotNull('t.stripe_transaction_id')
                    ->orWhereNotNull('t.last_payment_id');
            })
            ->whereRaw("LOWER(TRIM(COALESCE(t.payment_method, ''))) IN ('cash', 'card', 'cc', 'credit_card', 'credit card') OR t.stripe_transaction_id IS NOT NULL OR t.last_payment_id IS NOT NULL")
            ->selectRaw("
                CONCAT('transaction-', t.id) as row_key,
                COALESCE(i.invoice_number, CONCAT('Invoice #', COALESCE(t.invoice_id, 'N/A'))) as invoice_reference,
                CASE
                    WHEN COALESCE(t.appointment_id, i.appointment_id) IS NOT NULL THEN CONCAT('Appointment #', COALESCE(t.appointment_id, i.appointment_id))
                    ELSE NULL
                END as appointment_reference,
                {$transactionCustomerNameExpression} as customer_name,
                COALESCE(tu_profile.avatar_img, iu_profile.avatar_img, au_profile.avatar_img) as customer_avatar_path,
                t.amount as amount,
                CASE
                    WHEN LOWER(TRIM(COALESCE(t.payment_method, ''))) = 'cash' THEN 'Cash'
                    ELSE 'Credit Card'
                END as payment_type,
                'paid' as payment_status,
                COALESCE(t.tran_date, i.paid_at, t.created_at) as payment_date,
                CASE
                    WHEN LOWER(TRIM(COALESCE(t.payment_method, ''))) = 'cash'
                        AND t.stripe_transaction_id IS NULL
                        AND t.last_payment_id IS NULL
                    THEN NULL
                    ELSE COALESCE(t.stripe_transaction_id, t.last_payment_id)
                END as stripe_payment_id,
                COALESCE(t.tran_date, i.paid_at, t.created_at) as sort_at
            ");

        $paymentLinks = DB::table('payment_links as pl')
            ->leftJoin('invoices as i', 'i.id', '=', 'pl.invoice_id')
            ->leftJoin('appointments as pa', 'pa.id', '=', 'pl.appointment_id')
            ->leftJoin('appointments as ia', 'ia.id', '=', 'i.appointment_id')
            ->leftJoin('users as iu', 'iu.id', '=', 'i.customer_id')
            ->leftJoin('profiles as iu_profile', 'iu_profile.user_id', '=', 'iu.id')
            ->leftJoin('users as au', 'au.id', '=', DB::raw('COALESCE(pa.customer_id, ia.customer_id)'))
            ->leftJoin('profiles as au_profile', 'au_profile.user_id', '=', 'au.id')
            ->where(function ($query) {
                $query->whereNotNull('pl.stripe_payment_intent_id')
                    ->orWhereNotNull('pl.stripe_transaction_id')
                    ->orWhere('pl.payment_method', 'card')
                    ->orWhereIn('pl.status', ['pending', 'processing', 'completed', 'failed', 'expired']);
            })
            ->where(function ($query) {
                $query->whereRaw("
                    CASE
                        WHEN LOWER(TRIM(COALESCE(pl.status, ''))) = 'completed' THEN 'paid'
                        WHEN LOWER(TRIM(COALESCE(pl.status, ''))) IN ('pending', 'processing') THEN 'pending'
                        WHEN LOWER(TRIM(COALESCE(pl.status, ''))) IN ('failed', 'expired') THEN 'failed'
                        WHEN LOWER(TRIM(COALESCE(pl.status, ''))) = 'refunded' THEN 'refunded'
                        ELSE 'pending'
                    END <> 'paid'
                ")
                ->orWhereNotExists(function ($subQuery) {
                    $subQuery->selectRaw('1')
                        ->from('transactions as t2')
                        ->where(function ($nested) {
                            $nested->whereColumn('t2.invoice_id', 'pl.invoice_id')
                                ->orWhere(function ($stripeMatch) {
                                    $stripeMatch->whereRaw("COALESCE(t2.stripe_transaction_id, t2.last_payment_id) IS NOT NULL")
                                        ->whereColumn(DB::raw('COALESCE(t2.stripe_transaction_id, t2.last_payment_id)'), DB::raw('COALESCE(pl.stripe_transaction_id, pl.stripe_payment_intent_id)'));
                                });
                        })
                        ->where(function ($nested) {
                            $nested->whereIn('t2.payment_method', ['card', 'cc'])
                                ->orWhereNotNull('t2.stripe_transaction_id')
                                ->orWhereNotNull('t2.last_payment_id');
                        });
                });
            })
            ->selectRaw("
                CONCAT('payment-link-', pl.id) as row_key,
                COALESCE(i.invoice_number, CONCAT('Invoice #', COALESCE(pl.invoice_id, 'N/A'))) as invoice_reference,
                CASE
                    WHEN COALESCE(pl.appointment_id, i.appointment_id) IS NOT NULL THEN CONCAT('Appointment #', COALESCE(pl.appointment_id, i.appointment_id))
                    ELSE NULL
                END as appointment_reference,
                {$paymentLinkCustomerNameExpression} as customer_name,
                COALESCE(iu_profile.avatar_img, au_profile.avatar_img) as customer_avatar_path,
                pl.amount as amount,
                'Credit Card' as payment_type,
                CASE
                    WHEN LOWER(TRIM(COALESCE(pl.status, ''))) = 'completed' THEN 'paid'
                    WHEN LOWER(TRIM(COALESCE(pl.status, ''))) IN ('pending', 'processing') THEN 'pending'
                    WHEN LOWER(TRIM(COALESCE(pl.status, ''))) IN ('failed', 'expired') THEN 'failed'
                    WHEN LOWER(TRIM(COALESCE(pl.status, ''))) = 'refunded' THEN 'refunded'
                    ELSE 'pending'
                END as payment_status,
                COALESCE(pl.completed_at, pl.updated_at, pl.created_at) as payment_date,
                COALESCE(pl.stripe_transaction_id, pl.stripe_payment_intent_id) as stripe_payment_id,
                COALESCE(pl.completed_at, pl.updated_at, pl.created_at) as sort_at
            ");

        $legacyInvoices = DB::table('invoices as i')
            ->leftJoin('appointments as a', 'a.id', '=', 'i.appointment_id')
            ->leftJoin('services as s', 's.id', '=', 'a.service_id')
            ->leftJoin('service_categories as sc', 'sc.id', '=', 's.category_id')
            ->leftJoinSub($invoiceItemTotals, 'invoice_item_totals', function ($join) {
                $join->on('invoice_item_totals.invoice_id', '=', 'i.id');
            })
            ->leftJoin('users as iu', 'iu.id', '=', 'i.customer_id')
            ->leftJoin('profiles as iu_profile', 'iu_profile.user_id', '=', 'iu.id')
            ->where('i.status', 'paid')
            ->whereNotExists(function ($subQuery) {
                $subQuery->selectRaw('1')
                    ->from('transactions as t2')
                    ->whereColumn('t2.invoice_id', 'i.id')
                    ->where(function ($nested) {
                        $nested->whereIn('t2.payment_method', ['cash', 'card', 'cc'])
                            ->orWhereNotNull('t2.stripe_transaction_id')
                            ->orWhereNotNull('t2.last_payment_id');
                    });
            })
            ->whereNotExists(function ($subQuery) {
                $subQuery->selectRaw('1')
                    ->from('payment_links as pl2')
                    ->whereColumn('pl2.invoice_id', 'i.id')
                    ->whereRaw("LOWER(TRIM(COALESCE(pl2.status, ''))) IN ('completed', 'refunded')");
            })
            ->selectRaw("
                CONCAT('invoice-', i.id) as row_key,
                COALESCE(i.invoice_number, CONCAT('Invoice #', i.id)) as invoice_reference,
                CASE
                    WHEN i.appointment_id IS NOT NULL THEN CONCAT('Appointment #', i.appointment_id)
                    ELSE NULL
                END as appointment_reference,
                {$legacyCustomerNameExpression} as customer_name,
                iu_profile.avatar_img as customer_avatar_path,
                ROUND(
                    GREATEST(COALESCE(invoice_item_totals.subtotal, 0) - COALESCE(i.discount_amount, 0), 0)
                    + CASE
                        WHEN LOWER(COALESCE(sc.name, '')) LIKE '%boarding%'
                        THEN ROUND(GREATEST(COALESCE(invoice_item_totals.subtotal, 0) - COALESCE(i.discount_amount, 0), 0) * {$stateTaxRate} / 100, 2)
                        ELSE 0
                    END,
                    2
                ) as amount,
                'Cash' as payment_type,
                'paid' as payment_status,
                COALESCE(i.paid_at, i.updated_at, i.created_at) as payment_date,
                NULL as stripe_payment_id,
                COALESCE(i.paid_at, i.updated_at, i.created_at) as sort_at
            ");

        return $transactions
            ->unionAll($paymentLinks)
            ->unionAll($legacyInvoices);
    }

    private function resolveCustomerInitials(string $customerName): string
    {
        $words = preg_split('/\s+/', trim($customerName));

        if (empty($words)) {
            return '';
        }

        if (count($words) === 1) {
            return strtoupper(mb_substr($words[0], 0, 2));
        }

        return strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1));
    }

    private function getLocalBalanceSummary(): array
    {
        $cashAmount = floatval(Transaction::query()
            ->whereRaw("LOWER(TRIM(COALESCE(payment_method, ''))) = 'cash'")
            ->sum('amount'));
        $cardAmount = floatval($this->stripeEligibleTransactionsQuery()->sum('amount'));
        $reservedPayoutAmount = floatval(Payout::query()
            ->whereNotIn('status', ['failed', 'canceled'])
            ->sum('amount'));
        $availableAmount = $cashAmount + $cardAmount;
        $withdrawableAmount = max($cardAmount - $reservedPayoutAmount, 0);
        $pendingAmount = floatval(PaymentLink::query()
            ->whereIn('status', ['pending', 'processing'])
            ->sum('amount'));

        return [[
            'available' => [
                'display' => '$' . number_format($availableAmount, 2),
                'raw' => $availableAmount,
            ],
            'withdrawable' => [
                'display' => '$' . number_format($withdrawableAmount, 2),
                'raw' => $withdrawableAmount,
            ],
            'pending' => [
                'display' => '$' . number_format($pendingAmount, 2),
                'raw' => $pendingAmount,
            ],
            'breakdown' => [
                'cash' => [
                    'display' => '$' . number_format($cashAmount, 2),
                    'raw' => $cashAmount,
                ],
                'card' => [
                    'display' => '$' . number_format($cardAmount, 2),
                    'raw' => $cardAmount,
                ],
                'payouts' => [
                    'display' => '$' . number_format($reservedPayoutAmount, 2),
                    'raw' => $reservedPayoutAmount,
                ],
            ],
        ], null];
    }

    private function getWithdrawableAmount(): float
    {
        return round(max(
            floatval($this->stripeEligibleTransactionsQuery()->sum('amount'))
            - floatval(Payout::query()
                ->whereNotIn('status', ['failed', 'canceled'])
                ->sum('amount')),
            0
        ), 2);
    }

    private function stripeEligibleTransactionsQuery()
    {
        return Transaction::query()
            ->where(function ($query) {
                $query->whereRaw("LOWER(TRIM(COALESCE(payment_method, ''))) IN ('card', 'cc', 'credit_card', 'credit card', 'stripe')")
                    ->orWhereNotNull('stripe_transaction_id')
                    ->orWhereNotNull('last_payment_id');
            });
    }

    private function stripeRequestOptions(): array
    {
        $connectedAccountId = trim((string) config('services.stripe.connected_account_id'));

        if ($connectedAccountId === '') {
            return [];
        }

        return ['stripe_account' => $connectedAccountId];
    }

    private function assertPayoutAccountReady(string $currency): void
    {
        $account = $this->resolveStripePayoutAccount();

        if (!$account->payouts_enabled) {
            throw new \RuntimeException(
                sprintf(
                    'Stripe payouts are not enabled on the %s yet.',
                    $this->stripePayoutAccountLabel()
                )
            );
        }

        $externalAccounts = StripeAccount::allExternalAccounts($account->id, [
            'object' => 'bank_account',
            'limit' => 100,
        ]);

        foreach ($externalAccounts->data as $externalAccount) {
            $accountCurrency = strtolower((string) ($externalAccount->currency ?? ''));
            $status = strtolower((string) ($externalAccount->status ?? ''));

            if ($accountCurrency !== strtolower($currency)) {
                continue;
            }

            if ($status === '' || $status === 'new' || $status === 'validated' || $status === 'verified') {
                return;
            }
        }

        throw new \RuntimeException(
            sprintf(
                'The %s does not have a usable %s bank account linked for payouts.',
                $this->stripePayoutAccountLabel(),
                strtoupper($currency)
            )
        );
    }

    private function resolveStripePayoutAccount()
    {
        $connectedAccountId = trim((string) config('services.stripe.connected_account_id'));

        if ($connectedAccountId !== '') {
            return StripeAccount::retrieve($connectedAccountId);
        }

        return StripeAccount::retrieve();
    }

    private function stripePayoutAccountLabel(): string
    {
        $connectedAccountId = trim((string) config('services.stripe.connected_account_id'));

        if ($connectedAccountId !== '') {
            return "connected Stripe account {$connectedAccountId}";
        }

        return 'platform Stripe account';
    }

    private function formatPayoutErrorMessage(\Throwable $exception): string
    {
        $message = trim($exception->getMessage());

        if (str_contains(strtolower($message), "don't have any external accounts in that currency")) {
            return sprintf(
                'The %s does not have a USD bank account linked for payouts. Link a USD external bank account in Stripe, or set STRIPE_CONNECTED_ACCOUNT_ID if the bank account belongs to a connected account.',
                $this->stripePayoutAccountLabel()
            );
        }

        return $message;
    }
}
