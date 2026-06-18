@php
    $invoiceItems = collect($invoice->items ?? [])->filter();
    $customerName = trim((string) (($invoice->first_name ?? '') . ' ' . ($invoice->last_name ?? '')));
    $customerName = $customerName !== '' ? $customerName : (trim((string) (optional($appointment->customer)->name ?? '')) ?: 'Customer');

    $petNames = collect($appointment->familyPets ?? [])
        ->map(fn ($pet) => trim((string) ($pet->name ?? '')))
        ->filter()
        ->values();

    if ($petNames->isEmpty() && !empty($appointment->pet?->name)) {
        $petNames = collect([trim((string) $appointment->pet->name)]);
    }

    $petNameLabel = $petNames->isNotEmpty() ? $petNames->implode(', ') : 'Not provided';
    $serviceName = trim((string) (optional($appointment->service)->name ?? ''));

    $appointmentDateLabel = 'Not scheduled';
    if (!empty($appointment->date) && !empty($appointment->end_date) && $appointment->date !== $appointment->end_date) {
        $appointmentDateLabel = \Carbon\Carbon::parse($appointment->date)->format('M j, Y') . ' - ' . \Carbon\Carbon::parse($appointment->end_date)->format('M j, Y');
    } elseif (!empty($appointment->date)) {
        $appointmentDateLabel = \Carbon\Carbon::parse($appointment->date)->format('M j, Y');
    }

    $lineSubtotal = round((float) $invoiceItems->sum(fn ($item) => (float) ($item->price ?? 0)), 2);
    $discountAmount = round((float) ($invoice->discount_amount ?? 0), 2);
    $totalAmount = round((float) ($paymentLink->amount ?? 0), 2);
    $extraChargesAmount = round(max(0, $totalAmount - ($lineSubtotal - $discountAmount)), 2);
    $dueDateLabel = !empty($invoice->due_date) ? \Carbon\Carbon::parse($invoice->due_date)->format('M j, Y') : null;
    $issuedDateLabel = !empty($invoice->issued_at) ? \Carbon\Carbon::parse($invoice->issued_at)->format('M j, Y g:i A') : null;
    $backToPortalUrl = url('/');
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Secure Online Payment</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.7.2/dist/full.min.css" rel="stylesheet" type="text/css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://js.stripe.com/v3/"></script>
    <style>
        :root {
            --page-bg: #f4f6fb;
            --page-ink: #14213d;
            --panel-bg: rgba(255, 255, 255, 0.94);
            --panel-border: rgba(148, 163, 184, 0.2);
            --accent: #1d4ed8;
            --accent-soft: #dbeafe;
            --warm: #f59e0b;
            --success: #047857;
        }

        body {
            margin: 0;
            font-family: "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--page-ink);
            background:
                radial-gradient(circle at top left, rgba(29, 78, 216, 0.16), transparent 30%),
                radial-gradient(circle at bottom right, rgba(245, 158, 11, 0.16), transparent 26%),
                linear-gradient(180deg, #f8fafc 0%, #eef4ff 100%);
        }

        .hero-shell {
            position: relative;
            overflow: hidden;
        }

        .hero-shell::before,
        .hero-shell::after {
            content: "";
            position: absolute;
            border-radius: 9999px;
            filter: blur(10px);
            opacity: 0.65;
            pointer-events: none;
        }

        .hero-shell::before {
            top: -8rem;
            left: -4rem;
            width: 16rem;
            height: 16rem;
            background: rgba(29, 78, 216, 0.18);
        }

        .hero-shell::after {
            right: -4rem;
            bottom: -7rem;
            width: 18rem;
            height: 18rem;
            background: rgba(245, 158, 11, 0.15);
        }

        .glass-card {
            position: relative;
            background: var(--panel-bg);
            border: 1px solid var(--panel-border);
            border-radius: 28px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.12);
            backdrop-filter: blur(14px);
        }

        .summary-panel {
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 250, 252, 0.94) 100%);
        }

        .payment-panel {
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(239, 246, 255, 0.88) 100%);
        }

        #payment-element {
            padding: 16px;
            border: 1px solid #d1d5db;
            border-radius: 14px;
            background: #ffffff;
            box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.06);
        }

        .stripe-payment-card {
            border-radius: 18px;
            border: 1px solid #dbe3f0;
            background: #ffffff;
        }

        .spinner-dot {
            width: 18px;
            height: 18px;
            border: 3px solid rgba(255, 255, 255, 0.28);
            border-top-color: #ffffff;
            border-radius: 9999px;
            animation: spin 0.75s linear infinite;
        }

        .detail-chip {
            border: 1px solid rgba(148, 163, 184, 0.25);
            background: rgba(255, 255, 255, 0.85);
            border-radius: 18px;
        }

        .invoice-row + .invoice-row {
            border-top: 1px solid rgba(226, 232, 240, 0.9);
        }

        .metric-card {
            border-radius: 20px;
            border: 1px solid rgba(191, 219, 254, 0.8);
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.96) 0%, rgba(239, 246, 255, 0.96) 100%);
        }

        .success-orb {
            width: 5rem;
            height: 5rem;
            border-radius: 9999px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            background: radial-gradient(circle at top, #d1fae5, #a7f3d0);
            color: var(--success);
            box-shadow: 0 20px 40px rgba(4, 120, 87, 0.18);
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="hero-shell min-h-screen px-4 py-8 sm:px-6 lg:px-10 lg:py-12">
        <div class="mx-auto max-w-7xl">
            <section id="payment-shell" class="glass-card overflow-hidden">
                <div class="grid lg:grid-cols-[1.1fr_0.9fr]">
                    <div class="summary-panel p-6 sm:p-8 lg:p-10">
                        <div class="flex flex-col gap-5">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <span class="inline-flex rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-blue-700">Invoice payment</span>
                                    <h1 class="mt-4 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">Secure Online Payment</h1>
                                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">Review your invoice and complete payment securely.</p>
                                </div>
                                <div class="detail-chip px-4 py-3 text-right shadow-sm">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Amount due</p>
                                    <p class="mt-1 text-3xl font-bold text-slate-900">${{ number_format($totalAmount, 2) }}</p>
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="detail-chip px-4 py-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Customer</p>
                                    <p class="mt-2 text-base font-semibold text-slate-900">{{ $customerName }}</p>
                                </div>
                                <div class="detail-chip px-4 py-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Pet name</p>
                                    <p class="mt-2 text-base font-semibold text-slate-900">{{ $petNameLabel }}</p>
                                </div>
                                <div class="detail-chip px-4 py-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Appointment date</p>
                                    <p class="mt-2 text-base font-semibold text-slate-900">{{ $appointmentDateLabel }}</p>
                                </div>
                                <div class="detail-chip px-4 py-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Invoice reference</p>
                                    <p class="mt-2 text-base font-semibold text-slate-900">{{ $invoice->invoice_number ?? 'N/A' }}</p>
                                </div>
                            </div>

                            <div class="rounded-[24px] border border-slate-200/80 bg-white/90 p-5 shadow-sm">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <h2 class="text-lg font-semibold text-slate-900">Invoice summary</h2>
                                        <p class="mt-1 text-sm text-slate-500">
                                            @if($serviceName !== '')
                                                {{ $serviceName }}
                                            @else
                                                Service details
                                            @endif
                                            @if($dueDateLabel)
                                                · Due {{ $dueDateLabel }}
                                            @endif
                                        </p>
                                    </div>
                                    @if($issuedDateLabel)
                                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">Issued {{ $issuedDateLabel }}</span>
                                    @endif
                                </div>

                                <div class="mt-5 rounded-[20px] border border-slate-200/70 bg-slate-50/80">
                                    @forelse($invoiceItems as $item)
                                        @php
                                            $itemType = strtolower(trim((string) ($item->item_type ?? 'service')));
                                            $badgeClasses = match ($itemType) {
                                                'inventory' => 'bg-amber-100 text-amber-800',
                                                'service' => 'bg-blue-100 text-blue-700',
                                                default => 'bg-slate-200 text-slate-700',
                                            };
                                        @endphp
                                        <div class="invoice-row flex items-start justify-between gap-4 px-4 py-4">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p class="font-medium text-slate-900">{{ $item->item_name ?? 'Invoice item' }}</p>
                                                    <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] {{ $badgeClasses }}">{{ $itemType }}</span>
                                                </div>
                                            </div>
                                            <p class="shrink-0 font-semibold text-slate-900">${{ number_format((float) ($item->price ?? 0), 2) }}</p>
                                        </div>
                                    @empty
                                        <div class="px-4 py-5 text-sm text-slate-500">No line items were attached to this invoice.</div>
                                    @endforelse
                                </div>

                                <div class="mt-5 space-y-3 rounded-[20px] bg-slate-900 px-5 py-5 text-white">
                                    <div class="flex items-center justify-between text-sm text-slate-200">
                                        <span>Subtotal</span>
                                        <span>${{ number_format($lineSubtotal, 2) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between text-sm text-slate-200">
                                        <span>
                                            Discounts
                                            @if(!empty($invoice->discount_title))
                                                <span class="text-slate-400">({{ $invoice->discount_title }})</span>
                                            @endif
                                        </span>
                                        <span>- ${{ number_format($discountAmount, 2) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between text-sm text-slate-200">
                                        <span>Extra charges</span>
                                        <span>${{ number_format($extraChargesAmount, 2) }}</span>
                                    </div>
                                    <div class="h-px bg-white/10"></div>
                                    <div class="flex items-center justify-between text-lg font-semibold">
                                        <span>Total amount</span>
                                        <span>${{ number_format($totalAmount, 2) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="payment-panel p-6 sm:p-8 lg:p-10">
                        <div class="mx-auto max-w-xl">
                            <div class="metric-card p-5 shadow-sm">
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div>
                                        <span class="inline-flex rounded-full bg-white px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-blue-700 shadow-sm">Stripe secured</span>
                                        <h2 class="mt-4 text-2xl font-bold text-slate-900">Complete your payment</h2>
                                        <p class="mt-2 text-sm leading-6 text-slate-600">Enter your card details below. Payments are securely processed by Stripe.</p>
                                    </div>
                                    <div class="rounded-2xl border border-blue-100 bg-white px-4 py-3 text-right shadow-sm">
                                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Pay today</p>
                                        <p class="mt-1 text-2xl font-bold text-slate-900">${{ number_format($totalAmount, 2) }}</p>
                                    </div>
                                </div>
                            </div>

                            <form id="payment-form" class="mt-6 space-y-6 rounded-[24px] border border-slate-200/90 bg-white p-6 shadow-sm sm:p-7">
                                @csrf
                                <input type="hidden" id="payment-token" value="{{ $paymentLink->secure_token }}">
                                <input type="hidden" id="payment-client-secret" value="">

                                <div class="rounded-[20px] border border-blue-100 bg-blue-50/70 p-4 text-sm text-blue-900">
                                    <p class="font-semibold">Before you pay</p>
                                    <p class="mt-1 text-blue-800/80">Please confirm your invoice details on the left, then submit your card securely through Stripe.</p>
                                </div>

                                <div class="space-y-3">
                                    <label class="block text-sm font-semibold text-slate-700">Card details</label>
                                    <div id="payment-element" class="stripe-payment-card"></div>
                                    <div id="payment-errors" class="min-h-[1.25rem] text-sm text-red-600"></div>
                                </div>

                                <button id="submit-button" type="submit" class="inline-flex w-full items-center justify-center rounded-2xl bg-slate-900 px-5 py-4 text-base font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-400">
                                    <span id="button-text">Pay ${{ number_format($paymentLink->amount, 2) }}</span>
                                    <span id="spinner" class="hidden ml-3 inline-flex items-center gap-3">
                                        <span class="spinner-dot"></span>
                                        Processing
                                    </span>
                                </button>

                                <div class="flex flex-col gap-3 rounded-[20px] border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="font-semibold text-slate-800">Secure checkout</p>
                                        <p class="mt-1">Payments are securely processed by Stripe.</p>
                                    </div>
                                    <div class="rounded-full bg-white px-3 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 shadow-sm">SSL encrypted</div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </section>

            <section id="payment-message" class="hidden">
                <div class="mx-auto max-w-3xl glass-card overflow-hidden p-6 sm:p-8 lg:p-10">
                    <div class="text-center">
                        <div class="success-orb">
                            <span class="text-4xl font-bold">✓</span>
                        </div>
                        <p class="mt-6 text-sm font-semibold uppercase tracking-[0.22em] text-emerald-700">Payment confirmed</p>
                        <h2 class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl">Payment Successful</h2>
                        <p class="mx-auto mt-3 max-w-2xl text-base leading-7 text-slate-600">Thank you. Your payment has been received.</p>
                    </div>

                    <div class="mt-8 grid gap-4 sm:grid-cols-2">
                        <div class="detail-chip p-5 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Paid amount</p>
                            <p class="mt-2 text-3xl font-bold text-slate-900">${{ number_format($totalAmount, 2) }}</p>
                        </div>
                        <div class="detail-chip p-5 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Invoice reference</p>
                            <p class="mt-2 text-xl font-semibold text-slate-900">{{ $invoice->invoice_number ?? 'N/A' }}</p>
                            <p class="mt-1 text-sm text-slate-500">Appointment #{{ $appointment->id ?? 'N/A' }}</p>
                        </div>
                        <div class="detail-chip p-5 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Customer name</p>
                            <p class="mt-2 text-xl font-semibold text-slate-900">{{ $customerName }}</p>
                        </div>
                        <div class="detail-chip p-5 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Payment date and time</p>
                            <p id="payment-date-time" class="mt-2 text-xl font-semibold text-slate-900">Just now</p>
                        </div>
                    </div>

                    <div class="mt-8 rounded-[24px] border border-emerald-200 bg-emerald-50 p-5 text-emerald-900">
                        <p class="font-semibold">Next step</p>
                        <p class="mt-2 text-sm leading-6">A receipt-worthy confirmation has been recorded for your invoice. If you need any additional help with your appointment or billing, our team can assist.</p>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <script>
        const stripe = Stripe('{{ $stripePublicKey }}');
        const form = document.getElementById('payment-form');
        const submitButton = document.getElementById('submit-button');
        const paymentErrors = document.getElementById('payment-errors');
        const paymentMessage = document.getElementById('payment-message');
        const paymentShell = document.getElementById('payment-shell');
        const paymentDateTime = document.getElementById('payment-date-time');
        const token = document.getElementById('payment-token').value;
        let paymentElement;
        let elements;

        async function initializePayment() {
            submitButton.disabled = true;

            try {
                const response = await fetch('{{ route('payment.create-intent') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': document.querySelector('input[name="_token"]').value,
                    },
                    body: JSON.stringify({ token: token }),
                });

                const data = await response.json();

                if (!data.status) {
                    paymentErrors.textContent = data.message || 'Unable to initialize payment.';
                    return;
                }

                const clientSecret = data.clientSecret;
                document.getElementById('payment-client-secret').value = clientSecret;

                elements = stripe.elements({
                    clientSecret,
                    appearance: {
                        theme: 'stripe',
                        labels: 'floating',
                        variables: {
                            fontFamily: 'Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
                            colorBackground: '#ffffff',
                            colorPrimaryText: '#0f172a',
                            colorText: '#0f172a',
                            colorBorder: '#d1d5db',
                        },
                    },
                });
                paymentElement = elements.create('payment', { paymentMethodOrder: ['card'] });
                paymentElement.mount('#payment-element');
                submitButton.disabled = false;
            } catch (error) {
                paymentErrors.textContent = 'Unable to initialize payment: ' + error.message;
            }
        }

        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            submitButton.disabled = true;
            paymentErrors.textContent = '';
            paymentMessage.classList.add('hidden');
            document.getElementById('button-text').classList.add('hidden');
            document.getElementById('spinner').classList.remove('hidden');

            try {
                const { error, paymentIntent } = await stripe.confirmPayment({
                    elements,
                    confirmParams: {
                        return_url: window.location.href,
                    },
                    redirect: 'if_required',
                });

                if (error) {
                    paymentErrors.textContent = error.message;
                    throw error;
                }

                if (paymentIntent && paymentIntent.status === 'succeeded') {
                    const confirmResponse = await fetch('{{ route('payment.confirm') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': document.querySelector('input[name="_token"]').value,
                        },
                        body: JSON.stringify({
                            token: token,
                            paymentIntentId: paymentIntent.id,
                        }),
                    });

                    const confirmData = await confirmResponse.json();

                    if (confirmData.status) {
                        if (paymentDateTime) {
                            paymentDateTime.textContent = new Intl.DateTimeFormat(undefined, {
                                dateStyle: 'medium',
                                timeStyle: 'short',
                            }).format(new Date());
                        }
                        paymentMessage.classList.remove('hidden');
                        if (paymentShell) {
                            paymentShell.classList.add('hidden');
                        }
                    } else {
                        paymentErrors.textContent = confirmData.message || 'Payment confirmation failed.';
                        submitButton.disabled = false;
                    }
                } else {
                    paymentErrors.textContent = 'Payment could not be completed. Please try again.';
                    submitButton.disabled = false;
                }
            } catch (err) {
                submitButton.disabled = false;
            } finally {
                document.getElementById('button-text').classList.remove('hidden');
                document.getElementById('spinner').classList.add('hidden');
            }
        });

        initializePayment();
    </script>
</body>
</html>
