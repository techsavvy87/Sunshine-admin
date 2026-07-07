<?php

namespace App\Http\Controllers\web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Exception;
use App\Models\User;
use App\Models\AdditionalOwner;
use App\Models\Profile;
use App\Models\PetProfile;
use App\Models\PetVaccination;
use App\Models\PetCertificate;
use App\Models\Breed;
use App\Models\Color;
use App\Models\CoatType;
use App\Models\WeightRange;
use App\Models\Role;
use App\Models\CustomerInvite;
use App\Mail\AdminCustomerMessage;

class CustomerController extends Controller
{
    public function sendInvite(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = strtolower(trim((string) $request->email));

        $existingUser = User::where('email', $email)->first();
        $existingCustomer = null;

        if ($existingUser) {
            $existingCustomer = User::where('id', $existingUser->id)
                ->whereHas('roles', function ($query) {
                    $query->where('title', 'customer');
                })
                ->first();

            if (!$existingCustomer) {
                return back()->with([
                    'status' => 'fail',
                    'message' => 'This email is already used by a non-customer account.',
                ]);
            }
        }

        CustomerInvite::where('email', $email)
            ->whereNull('used_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', Carbon::now());
            })
            ->update(['expires_at' => Carbon::now()]);

        $rawToken = Str::random(64);
        $tokenHash = hash('sha256', $rawToken);

        $invite = CustomerInvite::create([
            'invited_by' => auth()->id(),
            'email' => $email,
            'token_hash' => $tokenHash,
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        $inviteLink = route('customer-invite.form', ['token' => $rawToken]);

        $message = "You have been invited to complete your customer registration/update at Sunshine Spot.\n\n";
        $message .= "Please use the secure invitation link below:\n{$inviteLink}\n\n";
        $message .= "This link will expire on " . Carbon::parse($invite->expires_at)->format('F j, Y g:i A') . ".";

        try {
            Mail::to($email)->send(new AdminCustomerMessage([
                'subject' => 'Customer Invitation',
                'customer_name' => 'Customer',
                'message' => $message,
                'cta_url' => $inviteLink,
                'cta_label' => 'Open Invitation',
                'sender_name' => 'Sunshine Spot Team',
            ]));
        } catch (\Throwable $exception) {
            return back()->with([
                'status' => 'fail',
                'message' => 'Invite could not be sent. Please verify mail settings and try again.',
            ]);
        }

        return back()->with([
            'status' => 'success',
            'message' => 'Invitation email sent successfully.',
        ]);
    }

    public function showInviteRegistrationForm(string $token)
    {
        $invite = $this->resolveInviteByToken($token);
        if (!$invite) {
            return view('customer-invites.invalid');
        }

        if ($invite->used_at || ($invite->expires_at && Carbon::parse($invite->expires_at)->isPast())) {
            return view('customer-invites.expired');
        }

        $weightRanges = WeightRange::all();
        $breeds = Breed::orderBy('name')->get();
        $colors = Color::orderBy('name')->get();
        $coatTypes = CoatType::orderBy('name')->get();
        $states = config('us_states', []);
        $vaccinationTypeOptions = [
            'Leptospirosis',
            'Rabies',
            'FVRCP',
            'Bordetella',
            'DHPP',
            'Annual Exam',
            'Annual Heartworm',
            'C5 Canine Vaccine',
            'Canine Coronavirus (CCoV)',
            'Canine Distemper',
            'Canine Hepatitis',
            'Canine Influenza',
            'Canine Parvovirus',
            'Crotalid',
            'Fecal Test',
            'Flea Prevention Medication',
            'Lyme',
            'Monthly Parasite Prevention',
        ];

        $breedOptions = $breeds->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
            ];
        })->values()->all();

        $colorOptions = $colors->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
            ];
        })->values()->all();

        $coatTypeOptions = $coatTypes->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
            ];
        })->values()->all();

        $weightRangeOptions = $weightRanges->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'min_weight' => $item->min_weight,
                'max_weight' => $item->max_weight,
            ];
        })->values()->all();

        $invitedCustomer = User::with([
            'profile',
            'additionalOwners',
            'pets.vaccinations',
            'pets.certificates',
        ])
            ->where('email', $invite->email)
            ->whereHas('roles', function ($query) {
                $query->where('title', 'customer');
            })
            ->first();

        $prefillCustomer = [];
        $prefillOwners = [];
        $prefillPets = [];

        if ($invitedCustomer) {
            $prefillCustomer = [
                'username' => $invitedCustomer->name,
                'avatar_img' => optional($invitedCustomer->profile)->avatar_img,
                'first_name' => optional($invitedCustomer->profile)->first_name,
                'last_name' => optional($invitedCustomer->profile)->last_name,
                'phone_number_1' => optional($invitedCustomer->profile)->phone_number_1,
                'phone_number_2' => optional($invitedCustomer->profile)->phone_number_2,
                'home_number' => optional($invitedCustomer->profile)->home_number,
                'work_number' => optional($invitedCustomer->profile)->work_number,
                'street_address' => optional($invitedCustomer->profile)->address,
                'city' => optional($invitedCustomer->profile)->city,
                'state' => optional($invitedCustomer->profile)->state,
                'zip_code' => optional($invitedCustomer->profile)->zip_code,
                'emergency_contact_info' => optional($invitedCustomer->profile)->emergency_contact_info,
            ];

            $prefillOwners = $invitedCustomer->additionalOwners->map(function ($owner) {
                return [
                    'name' => $owner->full_name,
                    'phone' => $owner->phone_number,
                ];
            })->values()->all();

            $prefillPets = $invitedCustomer->pets->map(function ($pet) use ($weightRanges) {
                return [
                    'pet_id' => $pet->id,
                    'pet_img' => $pet->pet_img,
                    'pet_name' => $pet->name,
                    'sex' => $pet->sex,
                    'type' => $pet->type,
                    'spay_neuter' => $pet->spay_neuter,
                    'birth_date' => $pet->birthdate ? Carbon::parse($pet->birthdate)->format('Y-m-d') : null,
                    'age' => $pet->age,
                    'breed' => $pet->breed_id,
                    'size' => $this->getWeightRangeIdByPetSize($pet->size, $weightRanges),
                    'weight' => $pet->weight,
                    'color' => $pet->color_id,
                    'coat_type' => $pet->coat_type_id,
                    'notes' => $pet->notes,
                    'veterinarian_name' => $pet->veterinarian_name,
                    'veterinarian_phone' => $pet->veterinarian_phone,
                    'existing_certificates' => $pet->certificates->map(function ($certificate) {
                        return [
                            'id' => $certificate->id,
                            'file_name' => $certificate->file_name,
                            'file_path' => $certificate->file_path,
                            'file_type' => $certificate->file_type,
                        ];
                    })->values()->all(),
                    'vaccinations' => $pet->vaccinations->map(function ($vaccination) {
                        return [
                            'type' => $vaccination->type,
                            'date' => $vaccination->date ? Carbon::parse($vaccination->date)->format('Y-m-d') : null,
                            'months' => $vaccination->months,
                        ];
                    })->values()->all(),
                ];
            })->values()->all();
        }

        return view('customer-invites.form', compact('invite', 'weightRanges', 'breeds', 'colors', 'coatTypes', 'states', 'token', 'breedOptions', 'colorOptions', 'coatTypeOptions', 'weightRangeOptions', 'vaccinationTypeOptions', 'prefillCustomer', 'prefillOwners', 'prefillPets', 'invitedCustomer'));
    }

    public function submitInviteRegistration(Request $request, string $token)
    {
        $invite = $this->resolveInviteByToken($token);
        if (!$invite || $invite->used_at || ($invite->expires_at && Carbon::parse($invite->expires_at)->isPast())) {
            return redirect()->route('customer-invite.form', ['token' => $token])->with([
                'status' => 'fail',
                'message' => 'This invite is invalid or expired.',
            ]);
        }

        $request->merge([
            'email' => $invite->email,
        ]);

        $invitedCustomer = User::with(['profile', 'additionalOwners', 'pets'])
            ->where('email', $invite->email)
            ->whereHas('roles', function ($query) {
                $query->where('title', 'customer');
            })
            ->first();

        $usernameRule = 'required|string|max:255|unique:users,name';
        if ($invitedCustomer) {
            $usernameRule = 'required|string|max:255|unique:users,name,' . $invitedCustomer->id;
        }

        $request->validate([
            'username' => $usernameRule,
            'password' => ($invitedCustomer ? 'nullable' : 'required') . '|string|min:8|confirmed',
            'temp_file_customer' => 'nullable|string',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone_number_1' => 'required|string|max:255',
            'phone_number_2' => 'nullable|string|max:255',
            'home_number' => 'nullable|string|max:255',
            'work_number' => 'nullable|string|max:255',
            'street_address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'zip_code' => 'nullable|string|max:255',
            'emergency_contact_info' => 'nullable|string|max:255',
            'owners' => 'nullable|string',
            'pets' => 'required|array|min:1',
            'pets.*.pet_id' => 'nullable|integer|exists:pet_profiles,id',
            'pets.*.pet_name' => 'required|string|max:255',
            'pets.*.sex' => 'required|in:male,female',
            'pets.*.type' => 'required|in:Dog,Cat',
            'pets.*.spay_neuter' => 'nullable|in:spayed,neutered,intact',
            'pets.*.birth_date' => 'nullable|date',
            'pets.*.age' => 'nullable|integer|min:0|max:50',
            'pets.*.breed' => 'required|exists:breeds,id',
            'pets.*.size' => 'required|exists:weight_ranges,id',
            'pets.*.weight' => 'required|numeric|min:0',
            'pets.*.color' => 'required|exists:colors,id',
            'pets.*.coat_type' => 'required|exists:coat_types,id',
            'pets.*.notes' => 'nullable|string',
            'pets.*.temp_file_pet' => 'nullable|string',
            'pets.*.veterinarian_name' => 'required|string|max:255',
            'pets.*.veterinarian_phone' => 'required|string|max:255',
            'pets.*.vaccinations' => 'nullable|array',
            'pets.*.vaccinations.*.type' => 'nullable|string|max:255',
            'pets.*.vaccinations.*.date' => 'nullable|date',
            'pets.*.vaccinations.*.months' => 'nullable|integer|min:1|max:120',
            'pets.*.delete_existing_certificate_ids' => 'nullable|string',
            'pets.*.certificate_files' => 'nullable|array',
            'pets.*.certificate_files.*' => 'nullable|file|max:10240',
        ]);

        DB::transaction(function () use ($request, $invite, $invitedCustomer) {
            if ($invitedCustomer) {
                $user = $invitedCustomer;
            } else {
                $user = new User();
                $user->email = $invite->email;
                $user->password = bcrypt($request->password);
                $user->email_verified_at = Carbon::now();
                $user->status = true;
                $user->block_reservations = false;
                $user->block_messages = false;
            }

            $user->name = $request->username;
            if ($invitedCustomer && $request->filled('password')) {
                $user->password = bcrypt($request->password);
            }
            $user->save();

            $profile = $user->profile ?: new Profile();
            $profile->user_id = $user->id;
            $profile->first_name = $request->first_name;
            $profile->last_name = $request->last_name;
            $profile->phone_number_1 = $request->phone_number_1;
            $profile->phone_number_2 = $request->phone_number_2;
            $profile->address = $request->street_address;
            $profile->city = $request->city;
            $profile->state = $request->state;
            $profile->zip_code = $request->zip_code;
            $profile->emergency_contact_info = $request->emergency_contact_info;
            $profile->home_number = $request->home_number;
            $profile->work_number = $request->work_number;

            if ($request->filled('temp_file_customer')) {
                $profile->avatar_img = $this->moveTempFileToPublic($request->temp_file_customer, 'profiles');
            }

            $profile->save();

            $customerRole = Role::where('title', 'customer')->first();
            if ($customerRole && !$user->roles()->where('roles.id', $customerRole->id)->exists()) {
                $user->roles()->attach($customerRole);
            }

            $owners = json_decode((string) $request->owners);
            AdditionalOwner::where('user_id', $user->id)->delete();
            if (is_array($owners)) {
                foreach ($owners as $owner) {
                    if (!empty($owner->name) && !empty($owner->phone)) {
                        $additionalOwner = new AdditionalOwner();
                        $additionalOwner->user_id = $user->id;
                        $additionalOwner->full_name = $owner->name;
                        $additionalOwner->phone_number = $owner->phone;
                        $additionalOwner->save();
                    }
                }
            }

            $existingPetIds = $user->pets()->pluck('id')->all();

            foreach ($request->pets as $petData) {
                $petId = isset($petData['pet_id']) ? (int) $petData['pet_id'] : null;
                $pet = null;

                if ($petId && in_array($petId, $existingPetIds, true)) {
                    $pet = PetProfile::find($petId);
                }

                if (!$pet) {
                    $pet = new PetProfile();
                    $pet->user_id = $user->id;
                    $pet->vaccine_status = 'submitted';
                    $pet->rating = null;
                    $pet->rating_notes = null;
                }

                $pet->name = $petData['pet_name'];
                $pet->sex = $petData['sex'];
                $pet->type = $petData['type'];
                $pet->spay_neuter = $petData['spay_neuter'] ?? null;
                $pet->birthdate = !empty($petData['birth_date']) ? Carbon::parse($petData['birth_date']) : null;
                $pet->age = !empty($petData['age']) ? $petData['age'] : null;
                $pet->breed_id = $petData['breed'];
                $pet->size = $this->getPetSizeByWeightRangeId($petData['size']);
                $pet->weight = $petData['weight'];
                $pet->color_id = $petData['color'];
                $pet->coat_type_id = $petData['coat_type'];
                $pet->notes = $petData['notes'] ?? null;
                $pet->veterinarian_name = $petData['veterinarian_name'];
                $pet->veterinarian_phone = $petData['veterinarian_phone'];

                if (!empty($petData['temp_file_pet'])) {
                    $pet->pet_img = $this->moveTempFileToPublic($petData['temp_file_pet'], 'pets');
                }

                $pet->save();

                PetVaccination::where('pet_profile_id', $pet->id)->delete();

                $deleteExistingCertificateIdsRaw = trim((string) ($petData['delete_existing_certificate_ids'] ?? ''));
                if ($deleteExistingCertificateIdsRaw !== '') {
                    $deleteExistingCertificateIds = collect(explode(',', $deleteExistingCertificateIdsRaw))
                        ->map(function ($id) {
                            return (int) trim((string) $id);
                        })
                        ->filter(function ($id) {
                            return $id > 0;
                        })
                        ->unique()
                        ->values();

                    if ($deleteExistingCertificateIds->isNotEmpty()) {
                        $certificatesToDelete = PetCertificate::where('pet_profile_id', $pet->id)
                            ->whereIn('id', $deleteExistingCertificateIds->all())
                            ->get();

                        foreach ($certificatesToDelete as $certificate) {
                            $filePath = 'pets/' . $certificate->file_path;
                            if (!empty($certificate->file_path) && Storage::disk('public')->exists($filePath)) {
                                Storage::disk('public')->delete($filePath);
                            }
                        }

                        PetCertificate::where('pet_profile_id', $pet->id)
                            ->whereIn('id', $deleteExistingCertificateIds->all())
                            ->delete();
                    }
                }

                $vaccinations = $petData['vaccinations'] ?? [];
                foreach ($vaccinations as $vaccination) {
                    $type = trim((string) ($vaccination['type'] ?? ''));
                    $date = trim((string) ($vaccination['date'] ?? ''));
                    $months = trim((string) ($vaccination['months'] ?? ''));
                    $hasAnyValue = $type !== '' || $date !== '' || $months !== '';

                    if (!$hasAnyValue) {
                        continue;
                    }

                    if ($type === '' || $date === '' || $months === '') {
                        throw ValidationException::withMessages([
                            'pets' => 'Please complete vaccination name, date, and months for each entered vaccination row.',
                        ]);
                    }

                    $petVaccination = new PetVaccination();
                    $petVaccination->pet_profile_id = $pet->id;
                    $petVaccination->type = strtolower($type);
                    $petVaccination->date = $date;
                    $petVaccination->months = (int) $months;
                    $petVaccination->save();
                }

                $certificateFiles = $petData['certificate_files'] ?? null;
                if (is_array($certificateFiles)) {
                    foreach ($certificateFiles as $file) {
                        if (!$file) {
                            continue;
                        }

                        $path = $file->store('public/pets');
                        $paths = explode('/', $path);

                        $certificate = new PetCertificate();
                        $certificate->pet_profile_id = $pet->id;
                        $certificate->file_path = end($paths);
                        $certificate->file_name = $file->getClientOriginalName();
                        $certificate->file_type = $file->getClientMimeType();
                        $certificate->file_size = $file->getSize();
                        $certificate->save();
                    }
                }
            }

            $invite->used_at = Carbon::now();
            $invite->used_by_user_id = $user->id;
            $invite->save();
        });

        return view('customer-invites.success', [
            'email' => $invite->email,
        ]);
    }

    public function listCustomers(Request $request)
    {
        $perPage = $request->get('per_page', 20);
        $search = $request->get('search');
        if (!empty($search)) {
            $customers = User::where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('profile', function ($q) use ($search) {
                        $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('phone_number_1', 'like', "%{$search}%")
                            ->orWhere('phone_number_2', 'like', "%{$search}%");
                    });
            })->whereHas('roles', function ($query) {
                $query->where('title', 'customer');
            })->orderBy('created_at', 'desc')->paginate($perPage);
        } else {
            $customers = User::whereHas('roles', function ($query) {
                            $query->where('title', 'customer');
                        })->orderBy('created_at', 'desc')->paginate($perPage);
        }
        return view('customers.index', compact('search', 'customers'));
    }

    public function addCustomer()
    {
        return view('customers.create');
    }

    public function processFileUpload(Request $request)
    {
        try {
            $request->validate([
                'avatar_img' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // 2MB max
            ]);

            // Handle file upload logic here
            $file = $request->file('avatar_img');

            // generate a unique file name
            $fileName = Str::random(40) . '.' . $file->getClientOriginalExtension();

            // Store in temporary directory
            $path = $file->storeAs('temp', $fileName, 'local');

            return response()->json([
                'temp_file' => $fileName,
                'original_name' => $file->getClientOriginalName(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'File upload failed: ' . $e->getMessage()
            ], 422);
        }
    }

    public function revertFileUpload(Request $request)
    {
        try {
            $tempFile = $request->getContent();

            if ($tempFile && Storage::disk('local')->exists('temp/' . $tempFile)) {
                Storage::disk('local')->delete('temp/' . $tempFile);
            }
            return response()->json(['message' => 'File reverted successfully.']);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'File deletion failed: ' . $e->getMessage()
            ], 422);
        }
    }

    public function createCustomer(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'phone_number_1' => 'required|string',
            'temp_file' => 'nullable|string',
        ]);

        // Create account
        $user = new User();
        $user->name = $request->username;
        $user->email = $request->email;
        $user->password = bcrypt($request->password);

        $isEmailVerified = $request->boolean('email_verified') ?? false;
        if ($isEmailVerified) {
            $user->email_verified_at = Carbon::now();
        }
        $user->status = $request->boolean('status') ?? false;
        $user->block_reservations = $request->boolean('block_reservations') ?? false;
        $user->block_messages = $request->boolean('block_messages') ?? false;
        $user->save();

        // Create profile
        $profile = new Profile();
        $profile->user_id = $user->id;
        $profile->first_name = $request->first_name;
        $profile->last_name = $request->last_name;
        $profile->phone_number_1 = $request->phone_number_1;
        $profile->phone_number_2 = $request->phone_number_2;
        $profile->address = $request->street_address;
        $profile->city = $request->city;
        $profile->state = $request->state;
        $profile->zip_code = $request->zip_code;
        $profile->emergency_contact_info = $request->emergency_contact_info;
        $profile->home_number = $request->home_number;
        $profile->work_number = $request->work_number;

        if ($request->filled('temp_file')) {
            $tempFile = $request->temp_file;
            $tempPath = 'temp/' . $tempFile;

            if (Storage::disk('local')->exists($tempPath)) {
                // Get file contents and ensure it's not null
                $fileContents = Storage::disk('local')->get($tempPath);

                if ($fileContents !== null) {
                    // Move the file to a permanent location
                    $permanentPath = 'profiles/' . $tempFile;
                    Storage::disk('public')->put($permanentPath, $fileContents);
                    Storage::disk('local')->delete($tempPath); // Delete the temporary file
                }
            }
            $profile->avatar_img = $tempFile; // Store the file name in the profile
        }

        $profile->save();

        // Assign customer role
        $customerRole = Role::where('title', 'customer')->first();
        if ($customerRole) {
            $user->roles()->attach($customerRole);
        }

        // Create additional owners
        $owners = json_decode($request->owners);
        foreach($owners as $owner)
        {
            if (!empty($owner->name) && !empty($owner->phone))
            {
                $additionalOwner = new AdditionalOwner;
                $additionalOwner->user_id = $user->id;
                $additionalOwner->full_name = $owner->name;
                $additionalOwner->phone_number = $owner->phone;
                $additionalOwner->save();
            }
        }

        // Optionally, redirect or return a response
        return redirect()->route('customers')->with([
            'status' => 'success',
            'message' => 'Customer created successfully!'
        ]);
    }

    public function editCustomer($id)
    {
        // Fetch the customer and their profile
        $customer = User::with(['appointmentCancellations.service', 'appointmentCancellations.cancelledBy'])->find($id);
        $perPage = (int) request('per_page', 5);
        $perPage = in_array($perPage, [5, 10, 20, 50], true) ? $perPage : 5;
        $invoices = $customer->invoices()
            ->whereIn('status', ['sent', 'paid'])
            ->orderByDesc('issued_at')
            ->paginate($perPage)
            ->withQueryString();
        return view('customers.update', compact('customer', 'invoices'));
    }

    public function customerInvoices($id)
    {
        $customer = User::findOrFail($id);
        $perPage = (int) request('per_page', 5);
        $perPage = in_array($perPage, [5, 10, 20, 50], true) ? $perPage : 5;
        $invoices = $customer->invoices()
            ->whereIn('status', ['sent', 'paid'])
            ->orderByDesc('issued_at')
            ->paginate($perPage)
            ->withQueryString();
        return view('customers.partials.invoice-list', compact('invoices'));
    }

    public function updateCustomer(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'username' => 'required|string',
            'email' => 'required|email|unique:users,email,' . $request->user_id,
            'password' => 'nullable|string',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'phone_number_1' => 'required|string',
            'avatar_action' => 'required|in:keep,change,delete',
            'temp_file' => 'nullable|string',
            'current_avatar' => 'nullable|string',
        ]);

        $user = User::findOrFail($request->user_id);

        // Update user account information
        $user->name = $request->username;
        $user->email = $request->email;
        // Update password only if provided
        if ($request->password) {
            $user->password = bcrypt($request->password);
        }
        $isEmailVerified = $request->boolean('email_verified') ?? false;
        if ($isEmailVerified) {
            $user->email_verified_at = Carbon::now();
        } else {
            $user->email_verified_at = null;
        }
        $user->status = $request->boolean('status') ?? false;
        $user->block_reservations = $request->boolean('block_reservations') ?? false;
        $user->block_messages = $request->boolean('block_messages') ?? false;

        $user->save();

        // Update profile information
        $profile = $user->profile;
        if (!$profile) {
            $profile = new Profile();
            $profile->user_id = $user->id;
        }

        $profile->first_name = $request->first_name;
        $profile->last_name = $request->last_name;
        $profile->phone_number_1 = $request->phone_number_1;
        $profile->phone_number_2 = $request->phone_number_2;
        $profile->address = $request->street_address;
        $profile->city = $request->city;
        $profile->state = $request->state;
        $profile->zip_code = $request->zip_code;
        $profile->emergency_contact_info = $request->emergency_contact_info;
        $profile->home_number = $request->home_number;
        $profile->work_number = $request->work_number;

        // Handle avatar based on action
        switch ($request->avatar_action) {
            case 'keep':
                // Do nothing - keep the current avatar
                break;

            case 'change':
                // Delete old avatar if exists
                if ($profile->avatar_img) {
                    $oldAvatarPath = 'profiles/' . $profile->avatar_img;
                    if (Storage::disk('public')->exists($oldAvatarPath)) {
                        Storage::disk('public')->delete($oldAvatarPath);
                    }
                }

                // Move new avatar from temp to permanent location
                if ($request->temp_file) {
                    $tempFile = $request->temp_file;
                    $tempPath = 'temp/' . $tempFile;

                    if (Storage::disk('local')->exists($tempPath)) {
                        $permanentPath = 'profiles/' . $tempFile;
                        Storage::disk('public')->put($permanentPath, Storage::disk('local')->get($tempPath));
                        Storage::disk('local')->delete($tempPath);
                        $profile->avatar_img = $tempFile;
                    }
                }
                break;

            case 'delete':
                // Delete current avatar
                if ($profile->avatar_img) {
                    $avatarPath = 'profiles/' . $profile->avatar_img;
                    if (Storage::disk('public')->exists($avatarPath)) {
                        Storage::disk('public')->delete($avatarPath);
                    }
                    $profile->avatar_img = null;
                }
                break;
        }

        $profile->save();

        // save additional owners
        $owners = json_decode($request->owners);
        // Collect IDs of submitted owners
        $submittedIds = [];
        foreach ($owners as $owner) {
            if (!empty($owner->id)) {
                $submittedIds[] = $owner->id;
            }
        }

        // Delete owners that are NOT in the submitted list
        AdditionalOwner::where('user_id', $user->id)
            ->whereNotIn('id', $submittedIds)
            ->delete();

        // Update or create owners
        foreach ($owners as $owner) {
            if (!empty($owner->id)) {
                // Update existing owner
                $additionalOwner = AdditionalOwner::find($owner->id);
                if ($additionalOwner) {
                    $additionalOwner->full_name = $owner->name;
                    $additionalOwner->phone_number = $owner->phone;
                    $additionalOwner->save();
                }
            } else {
                // Create new owner
                if (!empty($owner->name) || !empty($owner->phone)) {
                    $additionalOwner = new AdditionalOwner;
                    $additionalOwner->user_id = $user->id;
                    $additionalOwner->full_name = $owner->name;
                    $additionalOwner->phone_number = $owner->phone;
                    $additionalOwner->save();
                }
            }
        }

        return redirect()->route('customers')->with([
            'status' => 'success',
            'message' => 'Customer updated successfully!'
        ]);
    }

    public function deleteCustomer(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        $user = User::findOrFail($request->user_id);

        // Delete user's avatar if exists
        if ($user->profile && $user->profile->avatar_img) {
            $avatarPath = 'profiles/' . $user->profile->avatar_img;
            if (Storage::disk('public')->exists($avatarPath)) {
                Storage::disk('public')->delete($avatarPath);
            }
        }

        // Delete the user's pets' images if exists
        foreach ($user->pets as $pet) {
            if ($pet->pet_img) {
                $petAvatarPath = 'pets/' . $pet->pet_img;
                if (Storage::disk('public')->exists($petAvatarPath)) {
                    Storage::disk('public')->delete($petAvatarPath);
                }
            }
        }

        // Delete the user (this will cascade delete profile and role relationships and additional users and pets)
        $user->delete();

        return redirect()->route('customers')->with([
            'status' => 'success',
            'message' => 'Customer deleted successfully!'
        ]);
    }

    private function resolveInviteByToken(string $token): ?CustomerInvite
    {
        $tokenHash = hash('sha256', $token);
        return CustomerInvite::where('token_hash', $tokenHash)->first();
    }

    private function getPetSizeByWeightRangeId($weightRangeId): string
    {
        $weightRange = WeightRange::find($weightRangeId);
        if (!$weightRange) {
            return 'medium';
        }

        $clean = preg_replace('/[^A-Za-z0-9 ]/', '', $weightRange->name);
        $clean = strtolower((string) $clean);

        return trim($clean);
    }

    private function getWeightRangeIdByPetSize(?string $petSize, $weightRanges): ?int
    {
        if (!$petSize) {
            return null;
        }

        $normalizedPetSize = trim(strtolower((string) preg_replace('/[^A-Za-z0-9 ]/', '', $petSize)));
        foreach ($weightRanges as $weightRange) {
            $normalizedName = trim(strtolower((string) preg_replace('/[^A-Za-z0-9 ]/', '', $weightRange->name)));
            if ($normalizedName === $normalizedPetSize) {
                return (int) $weightRange->id;
            }
        }

        return null;
    }

    public function processInviteCustomerFileUpload(Request $request)
    {
        return $this->processTempImageUpload($request, 'avatar_img');
    }

    public function revertInviteCustomerFileUpload(Request $request)
    {
        return $this->revertTempImageUpload($request);
    }

    public function processInvitePetFileUpload(Request $request)
    {
        return $this->processTempImageUpload($request, 'pet_img');
    }

    public function revertInvitePetFileUpload(Request $request)
    {
        return $this->revertTempImageUpload($request);
    }

    private function processTempImageUpload(Request $request, string $field)
    {
        try {
            $request->validate([
                $field => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            $file = $request->file($field);
            $fileName = Str::random(40) . '.' . $file->getClientOriginalExtension();
            $file->storeAs('temp', $fileName, 'local');

            return response()->json([
                'temp_file' => $fileName,
                'original_name' => $file->getClientOriginalName(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'File upload failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    private function revertTempImageUpload(Request $request)
    {
        try {
            $tempFile = $request->getContent();

            if ($tempFile && Storage::disk('local')->exists('temp/' . $tempFile)) {
                Storage::disk('local')->delete('temp/' . $tempFile);
            }

            return response()->json(['message' => 'File reverted successfully.']);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'File deletion failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    private function moveTempFileToPublic(string $tempFile, string $targetDir): ?string
    {
        $tempPath = 'temp/' . $tempFile;
        if (!Storage::disk('local')->exists($tempPath)) {
            return null;
        }

        $fileContents = Storage::disk('local')->get($tempPath);
        if ($fileContents === null) {
            return null;
        }

        Storage::disk('public')->put($targetDir . '/' . $tempFile, $fileContents);
        Storage::disk('local')->delete($tempPath);

        return $tempFile;
    }

}
