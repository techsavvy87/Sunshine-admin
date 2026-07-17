<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\GroupClass;
use App\Models\Package;
use App\Models\CustomerPackage;
use App\Models\PetProfile;
use App\Services\InvoicePaymentService;
use Carbon\Carbon;
use Stripe;


class PaymentController extends Controller
{
    public function invoice($id)
    {
        $invoice = Invoice::find($id);
        if (!$invoice) {
            return response()->json([
                'status' => false,
                'message' => 'Invoice not found',
            ], 200);
        }

        $items = [];

        $appointment = Appointment::with(['pet.coatType', 'service.category', 'customer.profile'])
            ->find($invoice->appointment_id);

        $invoiceItems = InvoiceItem::where('invoice_id', $invoice->id)->get();
        foreach ($invoiceItems as $item) {
            $items[] = [
                'description' => $item->item_name,
                'price' => $item->price,
                'type' => $item->item_type
            ];
        }

        $invoice->items = $items;
        $coatPriceApplied = false;
        $totalCoatTypePrice = 0;
        $invoice->estimated_service_price = isset($appointment)
            ? $this->calculateEstimatedServicePrice($appointment, $coatPriceApplied, $totalCoatTypePrice)
            : 0;
        $invoice->coat_price_applied = $coatPriceApplied;
        $invoice->total_coat_type_price = floatval($totalCoatTypePrice);

        return response()->json([
            'status' => true,
            'message' => 'Checkout details retrieved successfully',
            'result' => $invoice
        ], 200);
    }

    public function invoices(Request $request)
    {
        $invoices = Invoice::where('customer_id', Auth::id())
            ->whereIn('status', ['sent', 'partially_paid', 'paid'])
                ->with(['appointment.pet', 'appointment.service'])
                ->get();

        return response()->json([
            'status' => true,
            'message' => 'Invoices retrieved successfully',
            'result' => $invoices
        ], 200);
    }

    public function checkoutDetail($apptId)
    {
        // Get the order details
        $appointment = Appointment::with(['pet.coatType', 'service.category', 'customer.profile', 'invoice'])
            ->find($apptId);
        if (!$appointment) {
            return response()->json([
                'status' => false,
                'message' => 'Appointment not found.',
                'result' => null
            ], 200);
        }

        $orderDetails = [];

        $service = Service::with(['category'])->find($appointment->service_id);
        if (!$service) {
            return response()->json([
                'status' => false,
                'message' => 'Service not found for this appointment.',
                'result' => null
            ], 200);
        }

        $isAlaCarte = isAlaCarteService($service);
        $petSize = $appointment->pet->size ?? 'medium';
        $chauffeurPricingData = buildChauffeurPricingData($appointment);
        $chauffeurServicePrices = $chauffeurPricingData['service_prices'] ?? [];

        $resolveAppointmentServicePrice = function ($service, $petSize, $metadata = null) use ($chauffeurServicePrices) {
            if (!$service) {
                return 0;
            }

            if (array_key_exists($service->id, $chauffeurServicePrices)) {
                return floatval($chauffeurServicePrices[$service->id]);
            }

            return getServicePrice($service, $petSize, $metadata);
        };

        if ($isAlaCarte && $appointment->metadata && isset($appointment->metadata['secondary_service_ids'])) {
            $secondaryServiceIds = explode(',', $appointment->metadata['secondary_service_ids']);
            $secondaryServices = Service::whereIn('id', $secondaryServiceIds)->get();

            $orderDetails['secondary_services'] = [];
            foreach ($secondaryServices as $secondaryService) {
                $orderDetails['secondary_services'][] = [
                    'avatar_img_url' => empty($secondaryService->avatar_img) ? '' : asset('storage/services/' . $secondaryService->avatar_img),
                    'price' => $resolveAppointmentServicePrice($secondaryService, $petSize),
                    'name' => $secondaryService->name
                ];
            }

            $orderDetails['main_service']['avatar_img_url'] = empty($service->avatar_img) ? '' : asset('storage/services/' . $service->avatar_img);
            $orderDetails['main_service']['price'] = 0;
            $orderDetails['main_service']['name'] = $service->name;
        } else {
            $orderDetails['main_service']['avatar_img_url'] = empty($service->avatar_img) ? '' : asset('storage/services/' . $service->avatar_img);
            $orderDetails['main_service']['price'] = isBoardingService($service)
                ? getBoardingServicePrice($service, $appointment)
                : $resolveAppointmentServicePrice($service, $petSize, $appointment->metadata);
            $orderDetails['main_service']['name'] = $service->name;
        }

        $additionalServiceIds = explode(',', $appointment->additional_service_ids ?? '');
        $additionalServices = Service::whereIn('id', $additionalServiceIds)->get();
        $orderDetails['additional_services'] = [];
        foreach ($additionalServices as $addService) {
            $orderDetails['additional_services'][] = [
                'price' => $resolveAppointmentServicePrice($addService, $petSize),
                'name' => $addService->name
            ];
        }

        $invoiceId = $appointment->invoice->id ?? null;
        $inventoryItems = InvoiceItem::where('invoice_id', $invoiceId)->where('item_type', 'inventory')->get();
        $orderDetails['inventory_items'] = [];
        foreach ($inventoryItems as $item) {
            $orderDetails['inventory_items'][] = [
                'item_name' => $item->item_name,
                'price' => $item->price,
            ];
        }

        $coatPriceApplied = false;
        $totalCoatTypePrice = 0;
        $orderDetails['estimated_service_price'] = $this->calculateEstimatedServicePrice($appointment, $coatPriceApplied, $totalCoatTypePrice);
        $orderDetails['coat_price_applied'] = $coatPriceApplied;
        $orderDetails['total_coat_type_price'] = floatval($totalCoatTypePrice);
        $orderDetails['invoice_id'] = isset($appointment->invoice) ? $appointment->invoice->id : null;

        $result['order_details'] = $orderDetails;

        // Get the customer details
        $result['customer_details'] = $this->getCustomerDetails();

        // Check if there is an existing discount for this appointment
        if ($appointment->invoice && $appointment->invoice->discount_amount) {
            $result['discount'] = [
                'title' => $appointment->invoice->discount_title ?? '',
                'amount' => $appointment->invoice->discount_amount
            ];
        } elseif (isBoardingService($appointment->service)) {
            $boardingPricing = getBoardingPricingBreakdown($appointment, null, $appointment->service);
            if (($boardingPricing['family_discount_amount'] ?? 0) > 0) {
                $result['discount'] = [
                    'title' => $boardingPricing['family_discount_title'] ?? 'Multi-Pet Discount',
                    'amount' => floatval($boardingPricing['family_discount_amount']),
                ];
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Appointment checkout details retrieved successfully.',
            'result' => $result
        ], 200);
    }

    public function checkoutClass(Request $request)
    {
        $request->validate([
            'class_ids' => 'required|array|min:1',
            'pet_id' => 'required|integer',
        ]);

        $classIds = $request->class_ids;
        $petId = $request->pet_id;

        $totalPrice = 0;
        foreach ($classIds as $classId) {
            $groupClass = GroupClass::find($classId);
            if ($groupClass) {
                $totalPrice += floatval($groupClass->price ?? 0);
            }
        }

        $groupClasses = GroupClass::whereIn('id', $classIds)->get();
        $service = Service::whereRaw('LOWER(name) LIKE ?', ['%class%'])->first();
        if (empty($service)) {
            return response()->json([
                'status' => false,
                'message' => 'Service for group classes not found.',
                'result' => null
            ], 200);
        }

        $pet = PetProfile::find($petId);
        $ownerId = PetProfile::where('id', $petId)->value('user_id');
        $discountPreview = buildDiscountPreview($totalPrice, $ownerId, $service->id);
        $discountAmount = $discountPreview['discount_amount'];

        $orderDetails['service_id'] = $service->id;
        $orderDetails['service_img_url'] = empty($service->avatar_img) ? '' : asset('storage/services/' . $service->avatar_img);
        $orderDetails['group_classes'] = $groupClasses;
        $orderDetails['total_price'] = $totalPrice;
        $result['order_details'] = $orderDetails;

        if ($discountAmount > 0) {
            $result['discount'] = [
                'title' => $discountPreview['discount_title'] ?? '',
                'amount' => $discountAmount,
            ];
        }

        // Get the customer details
        $result['customer_details'] = $this->getCustomerDetails();

        return response()->json([
            'status' => true,
            'message' => 'Total price for selected classes calculated successfully.',
            'result' => $result
        ], 200);
    }

    public function checkoutPackage(Request $request)
    {
        $request->validate([
            'package_id' => 'required',
        ]);

        $packageId = $request->package_id;

        $totalPrice = 0;
        $package = Package::find($packageId);
        if (!isset($package)) {
            return response()->json([
                'status' => false,
                'message' => 'Package not found.',
                'result' => null
            ], 200);
        }

        $service = Service::whereRaw('LOWER(name) LIKE ?', ['%package%'])->first();
        if (empty($service)) {
            return response()->json([
                'status' => false,
                'message' => 'Service for package not found.',
                'result' => null
            ], 200);
        }

        $package->package_img_url = empty($package->image) ? '' : asset('storage/services/' . $package->image);
        // Get services associated with this package
        $serviceIds = $package->service_ids ? explode(',', $package->service_ids) : [];
        if (!empty($serviceIds)) {
            $services = Service::whereIn('id', $serviceIds)
                ->where('status', 'active')
                ->with('category')
                ->get();
            $package->services = $services;
        } else {
            $package->services = collect([]);
        }

        $orderDetails['package'] = $package;
        $orderDetails['service_id'] = $service->id;
        $result['order_details'] = $orderDetails;

        // Get the customer details
        $result['customer_details'] = $this->getCustomerDetails();

        return response()->json([
            'status' => true,
            'message' => 'Package details retrieved successfully.',
            'result' => $result
        ], 200);
    }

    public function setStripe(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|string|email',
            'amount' => 'required',
            'line1' => 'required|string',
            'line2' => 'nullable|string',
            'country' => 'required|string',
            'city' => 'required|string',
            'state' => 'required|string',
            'postalCode' => 'required|string',
        ]);

        $name = $request->name;
        $email = $request->email;
        $amount = $request->amount;
        $line1 = $request->line1;
        $line2 = $request->line2;
        $country = $request->country;
        $city = $request->city;
        $state = $request->state;
        $postalCode = $request->postalCode;

        $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));

        $payment = Payment::where('user_id', Auth::id())->first();
        if (isset($payment)) {
            // Use an existing Customer ID if this is a returning customer.
            $stripeCustomerId = $payment->stripe_customer_id;
            if (!empty($stripeCustomerId)) {
                $stripe->customers->update(
                    $stripeCustomerId,
                    [
                        'name' => $name,
                        'email' => $email,
                        'address' => [
                            'line1' => $line1,
                            'line2' => $line2,
                            'city' => $city,
                            'state' => $state,
                            'postal_code' => $postalCode,
                            'country' => $country
                        ]
                    ]
                );
            } else {
                // Create a new Customer
                $customer = $stripe->customers->create([
                    'name' => $name,
                    'email' => $email,
                    'address' => [
                        'line1' => $line1,
                        'line2' => $line2,
                        'city' => $city,
                        'state' => $state,
                        'postal_code' => $postalCode,
                        'country' => $country
                    ]
                ]);
                $stripeCustomerId = $customer->id;

                $payment->stripe_customer_id = $stripeCustomerId;
                $payment->save();
            }
        } else {
            // Create a new Customer
            $customer = $stripe->customers->create([
                'name' => $name,
                'email' => $email,
                'address' => [
                    'line1' => $line1,
                    'line2' => $line2,
                    'city' => $city,
                    'state' => $state,
                    'postal_code' => $postalCode,
                    'country' => $country
                ]
            ]);
            $stripeCustomerId = $customer->id;

            $payment = new Payment;
            $payment->user_id = Auth::id();
            $payment->stripe_customer_id = $stripeCustomerId;
            $payment->save();
        }

        $ephemeralKey = $stripe->ephemeralKeys->create(
            ['customer' => $stripeCustomerId,],
            ['stripe_version' => '2024-11-20.acacia',]
        );
        $paymentIntent = $stripe->paymentIntents->create([
            'amount' => $amount,
            'currency' => 'usd',
            'customer' => $stripeCustomerId,
            'payment_method_types' => ['card'],
            'payment_method_options' => [
                'card' => [
                    'setup_future_usage' => 'off_session',
                ],
            ],
        ]);

        $result = [
            'paymentId' => $paymentIntent->id,
            'paymentIntent' => $paymentIntent->client_secret,
            'ephemeralKey' => $ephemeralKey->secret,
            'customer' => $stripeCustomerId,
            'publishableKey' => env('STRIPE_KEY')
        ];

        return response()->json([
            'status' => true,
            'message' => "Set up the stripe payment intent successfully",
            'result' => $result
        ], 200);
    }

    public function completePayment(Request $request)
    {
        $request->validate([
            'amount' => 'required',
            'lastPaymentId' => 'required'
        ]);

        $amount = $request->amount;
        $lastPaymentId = $request->lastPaymentId;
        $appointmentId = $request->appointmentId ?? null;
        $invoiceId = $request->invoiceId ?? null;
        $classIds = $request->classIds ?? null;
        $packageId = $request->packageId ?? null;

        $transactionNote = '';

        // Update the appointment status
        if (isset($appointmentId)) {
            $appointment = Appointment::find($appointmentId);
            if ($appointment) {
                $appointment->status = 'finished';
                $appointment->save();
            }
            $transactionNote = 'Payment completed for the booked appointment.';
        }

        $invoice = null;
        if (isset($invoiceId)) {
            $invoice = Invoice::find($invoiceId);
        }

        // If there are class IDs, create the appointments for each class's schedule
        if (isset($classIds)) {
            $transactionNote = 'Payment completed for the booked group classes.';
            handleGroupClasses($classIds, Auth::id(), $request->petId, $request->serviceId, $request->staff ?? null);
        }

        // If there is a package ID, handle customer package enrollment
        if (isset($packageId)) {
            $package = Package::find($packageId);
            $days = $package ? intval($package->days ?? 0) : 0;

            $customerPackage = CustomerPackage::where('customer_id', Auth::id())
                ->where('package_id', $packageId)
                ->first();

            if ($customerPackage) {
                $customerPackage->original_days += $days;
                $customerPackage->remaining_days += $days;
                $customerPackage->save();
            } else {
                $customerPackage = new CustomerPackage;
                $customerPackage->customer_id = Auth::id();
                $customerPackage->package_id = $packageId;
                $customerPackage->remaining_days = $days;
                $customerPackage->original_days = $days;
                $customerPackage->save();
            }

            $transactionNote = 'Payment completed for the purchased package.';
        }

        if ($invoice) {
            $paymentService = app(InvoicePaymentService::class);
            $paymentResult = $paymentService->recordPayment($invoice, [
                'appointment_id' => $appointmentId,
                'user_id' => Auth::id(),
                'tran_date' => Carbon::now(),
                'amount' => floatval($amount),
                'payment_method' => 'stripe',
                'notes' => $transactionNote,
                'last_payment_id' => $lastPaymentId,
            ]);

            if ($paymentResult['created']) {
                $paymentService->createAdminPaymentNotifications($invoice->fresh(), $paymentResult['transaction'], $paymentResult['summary']);
            }

            if ($invoice->appointment_id && function_exists('appointment_audit_log')) {
                $statusLabel = ($paymentResult['summary']['status'] ?? '') === 'paid' ? 'Paid' : 'Partially Paid';
                appointment_audit_log($invoice->appointment_id, "Invoice payment received via API. Status: {$statusLabel}. Invoice #{$invoice->invoice_number}.");
            }
        } else {
            $transaction = new Transaction;
            $transaction->appointment_id = $appointmentId;
            $transaction->invoice_id = $invoiceId;
            $transaction->user_id = Auth::id();
            $transaction->tran_date = Carbon::now();
            $transaction->amount = floatval($amount);
            $transaction->payment_method = 'stripe';
            $transaction->notes = $transactionNote;
            $transaction->last_payment_id = $lastPaymentId;
            $transaction->save();
        }

        return response()->json([
            'status' => true,
            'message' => 'Payment has been completed.',
            'result' => Null
        ], 200);
    }

    private function calculateEstimatedServicePrice(Appointment $appointment, &$coatPriceApplied = false, &$totalCoatTypePrice = 0): float
    {
        $coatPriceApplied = false;
        $totalCoatTypePrice = 0;

        if (!$appointment->service) {
            return floatval($appointment->estimated_price ?? 0);
        }

        $chauffeurPricingData = buildChauffeurPricingData($appointment);
        $chauffeurServicePrices = $chauffeurPricingData['service_prices'] ?? [];

        $resolveAppointmentServicePrice = function ($service, $petSize, $metadata = null, $referenceDate = null) use ($chauffeurServicePrices) {
            if (!$service) {
                return 0;
            }

            if (array_key_exists($service->id, $chauffeurServicePrices)) {
                return floatval($chauffeurServicePrices[$service->id]);
            }

            return getServicePrice($service, $petSize, $metadata, $referenceDate);
        };

        $estimatedTotal = 0;
        $petSize = $appointment->pet->size ?? 'medium';
        $isGroupClasses = isGroupClassService($appointment->service);
        $isAlaCarte = isAlaCarteService($appointment->service);
        $referenceDate = $appointment->date ?? now();

        $groupClassIds = [];
        if ($isGroupClasses && $appointment->metadata && isset($appointment->metadata['group_class_ids'])) {
            $groupClassIds = explode(',', $appointment->metadata['group_class_ids']);
        }

        $secondaryServiceIds = [];
        if ($isAlaCarte && $appointment->metadata && isset($appointment->metadata['secondary_service_ids'])) {
            $secondaryServiceIds = explode(',', $appointment->metadata['secondary_service_ids']);
        }

        if ($isGroupClasses && !empty($groupClassIds)) {
            foreach ($groupClassIds as $classId) {
                $groupClass = GroupClass::find($classId);
                if ($groupClass) {
                    $estimatedTotal += floatval($groupClass->price ?? 0);
                }
            }
        } elseif ($isAlaCarte && !empty($secondaryServiceIds)) {
            foreach ($secondaryServiceIds as $serviceId) {
                if (!empty($serviceId)) {
                    $service = Service::find($serviceId);
                    if ($service) {
                        $estimatedTotal += $resolveAppointmentServicePrice($service, $petSize, null, $referenceDate);
                    }
                }
            }

            $secondaryServiceIdSet = collect($secondaryServiceIds)
                ->map(fn ($serviceId) => (string) trim($serviceId))
                ->filter()
                ->values();

            $additionalServiceIds = collect(explode(',', $appointment->additional_service_ids ?? ''))
                ->map(fn ($serviceId) => (string) trim($serviceId))
                ->filter()
                ->reject(fn ($serviceId) => $secondaryServiceIdSet->contains($serviceId))
                ->values();

            foreach ($additionalServiceIds as $serviceId) {
                $service = Service::find($serviceId);
                if ($service) {
                    $estimatedTotal += $resolveAppointmentServicePrice($service, $petSize, null, $referenceDate);
                }
            }
        } else {
            if (isBoardingService($appointment->service)) {
                $pricingPets = $appointment->family_pets;

                if ($pricingPets->isEmpty() && $appointment->pet) {
                    $pricingPets = collect([$appointment->pet]);
                }

                foreach ($pricingPets as $pet) {
                    $petSize = $pet->size ?? ($appointment->pet->size ?? 'medium');
                    $priceAppointment = clone $appointment;
                    $priceAppointment->pet_id = $pet->id ?? $appointment->pet_id;

                    $boardingPrice = getBoardingServicePrice($appointment->service, $priceAppointment, $appointment->service);
                    $petTotal = $boardingPrice !== null
                        ? $boardingPrice
                        : $resolveAppointmentServicePrice($appointment->service, $petSize, $appointment->metadata, $referenceDate);

                    $additionalServiceIds = explode(',', $appointment->additional_service_ids ?? '');
                    foreach ($additionalServiceIds as $serviceId) {
                        if (!empty($serviceId)) {
                            $service = Service::find($serviceId);
                            if ($service) {
                                $petTotal += $resolveAppointmentServicePrice($service, $petSize, null, $referenceDate);
                            }
                        }
                    }

                    $estimatedTotal += $petTotal;
                }
            } else {
                $estimatedTotal = $resolveAppointmentServicePrice($appointment->service, $petSize, $appointment->metadata, $referenceDate);

                $additionalServiceIds = explode(',', $appointment->additional_service_ids ?? '');
                foreach ($additionalServiceIds as $serviceId) {
                    if (!empty($serviceId)) {
                        $service = Service::find($serviceId);
                        if ($service) {
                            $estimatedTotal += $resolveAppointmentServicePrice($service, $petSize, null, $referenceDate);
                        }
                    }
                }
            }
        }

        $isPetDoubleCoated = (bool) (optional(optional($appointment->pet)->coatType)->is_double_coated ?? false);
        if ($isPetDoubleCoated) {
            $coatFeeServiceIds = array_filter(array_unique(array_merge(
                [$appointment->service_id],
                !empty($appointment->second_service_id) ? [$appointment->second_service_id] : [],
                !empty($appointment->additional_service_ids) ? explode(',', $appointment->additional_service_ids) : [],
                ($isAlaCarte && !empty($secondaryServiceIds)) ? $secondaryServiceIds : []
            )));

            $coatServices = Service::whereIn('id', $coatFeeServiceIds)->get();
            foreach ($coatServices as $coatService) {
                if ((bool) $coatService->is_double_coated) {
                    $coatPrice = floatval($coatService->coat_type_price ?? 0);
                    $estimatedTotal += $coatPrice;
                    $totalCoatTypePrice += $coatPrice;
                }
            }

            $coatPriceApplied = $totalCoatTypePrice > 0;
        }

        return floatval($estimatedTotal);
    }

    private function getCustomerDetails()
    {
        $customerDetails = [];
        $customerDetails['name'] = '';
        $customerDetails['email'] = '';
        $customerDetails['line1'] = '';
        $customerDetails['line2'] = '';
        $customerDetails['city'] = '';
        $customerDetails['state'] = '';
        $customerDetails['postal_code'] = '';
        $customerDetails['country'] = '';

        $payment = Payment::where('user_id', Auth::id())->first();
        if (isset($payment)) {
            $stripeCustomerId = $payment->stripe_customer_id;

            if (!empty($stripeCustomerId)) {
                $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));

                try {
                    $customerResp = $stripe->customers->retrieve($stripeCustomerId, []);
                    $address = $customerResp->address;
                    $name = $customerResp->name;
                    $email = $customerResp->email;

                    if (isset($name)) {
                        $customerDetails['name'] = $name;
                    }
                    if (isset($email)) {
                        $customerDetails['email'] = $email;
                    }

                    if (isset($address)) {
                        $customerDetails['city'] = $address->city;
                        $customerDetails['country'] = $address->country;
                        $customerDetails['line1'] = $address->line1;
                        $customerDetails['line2'] = $address->line2;
                        $customerDetails['postal_code'] = $address->postal_code;
                        $customerDetails['state'] = $address->state;
                    }
                } catch (\Stripe\Exception\InvalidRequestException $e) {
                    $payment->stripe_customer_id = NULL;
                    $payment->save();
                }
            }
        }

        return $customerDetails;
    }
}
