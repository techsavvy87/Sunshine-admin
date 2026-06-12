<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Profile;
use App\Models\AdditionalOwner;
use App\Models\PetProfile;
use App\Models\PetCertificate;
use App\Models\PetVaccination;
use App\Models\Breed;
use App\Models\Color;
use App\Models\CoatType;
use App\Models\WeightRange;
use App\Models\Notification;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $profile = Profile::where('user_id', Auth::id())->first();
        if (!$profile) {
            return response()->json([
                'status' => false,
                'message' => 'Profile not found',
            ], 200);
        }

        $profile->avatar_img_url = empty($profile->avatar_img) ? '' : asset('storage/profiles/' . $profile->avatar_img);
        return response()->json([
            'status' => true,
            'message' => 'Fetched the user profile successfully',
            'result' => $profile
        ], 200);
    }

    public function update(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'phone_number_1' => 'required|string',
            'avatar_action' => 'required|in:keep,change,delete',
            'profile_avatar' => 'image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
        ]);

        $userId = Auth::id();
        $profile = Profile::where('user_id', $userId)->first();

        if (!$profile) {
            $profile = new Profile();
            $profile->user_id = $userId;
        }

        $profile->first_name = $request->first_name;
        $profile->last_name = $request->last_name;
        $profile->phone_number_1 = $request->phone_number_1;
        $profile->phone_number_2 = $request->phone_number_2;
        $profile->address = $request->address;
        $profile->city = $request->city;
        $profile->state = $request->state;
        $profile->zip_code = $request->zip_code;

        // Handle avatar based on action
        switch ($request->avatar_action) {
            case 'change':
                if ($request->hasFile('profile_avatar')) {
                    if (!empty($profile->avatar_img)) {
                        Storage::delete('public/profiles/' . $profile->avatar_img);
                    }

                    $path = $request->profile_avatar->store('public/profiles');
                    $paths = explode("/", $path);
                    $profile->avatar_img = end($paths);
                }
                break;
            case 'delete':
                if (!empty($profile->avatar_img)) {
                    Storage::delete('public/profiles/' . $profile->avatar_img);
                }
                $profile->avatar_img = null;
                break;
        }

        $profile->save();

        // Notify admin with 'send/receive messages' permission about profile update
        $users = User::select('users.*')
            ->join('role_user', 'users.id', '=', 'role_user.user_id')
            ->join('roles_permissions', 'role_user.role_id', '=', 'roles_permissions.role_id')
            ->where('roles_permissions.permission_id', 15)
            ->where('roles_permissions.can_read', 1)
            ->distinct()
            ->get();
        foreach ($users as $user) {
            $notification = new Notification;
            $notification->user_id = $user->id;
            $notification->sender_id = Auth::id();
            $notification->title = "Customer Profile Updated";
            $notification->message = "A customer has updated their profile.";
            $notification->type = "customer_profile";
            $notification->is_read = false;
            $notification->save();
        }

        return response()->json([
            'status' => true,
            'message' => 'Updated the user profile successfully',
            'result' => $profile
        ], 200);
    }

    public function listAdditionalOwners(Request $request)
    {
        $additionalOwners = AdditionalOwner::where('user_id', Auth::id())->get();
        return response()->json([
            'status' => true,
            'message' => 'Fetched additional owners successfully',
            'result' => $additionalOwners
        ], 200);
    }

    public function updateAdditionalOwners(Request $request)
    {
        $request->validate([
            'owners' => 'array',
            'owners.*.name' => 'required|string',
            'owners.*.phone' => 'required|string',
        ]);

        $owners = $request->owners;

        // collect Ids of submitted additional owners
        $updatedOwnerIds = [];
        foreach($owners as $owner) {
            if (!empty($owner['id'])) {
                $updatedOwnerIds[] = $owner['id'];
            }
        }

        // Delete owners that are NOT in the submitted list
        AdditionalOwner::where('user_id', Auth::id())
            ->whereNotIn('id', $updatedOwnerIds)
            ->delete();

        // Update or create attributes
        foreach ($owners as $owner) {
            $existingOwner = AdditionalOwner::find($owner['id']);
            if ($existingOwner) {
                $existingOwner->full_name = $owner['name'];
                $existingOwner->phone_number = $owner['phone'];
                $existingOwner->save();
            } else {
                $newOwner = new AdditionalOwner;
                $newOwner->user_id = Auth::id();
                $newOwner->full_name = $owner['name'];
                $newOwner->phone_number = $owner['phone'];
                $newOwner->save();
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Updated additional owners successfully'
        ], 200);
    }

    public function listBreeds(Request $request)
    {
        $search = $request->input('query');
        $perPage = $request->input('per_page', 20);
        $query = Breed::query();

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $breeds = $query->orderBy('name')->paginate($perPage);

        return response()->json($breeds);
    }

    public function listColors(Request $request)
    {
        $search = $request->input('query');
        $perPage = $request->input('per_page', 20);
        $query = Color::query();

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $colors = $query->orderBy('name')->paginate($perPage);

        return response()->json($colors);
    }

    public function listCoatTypes(Request $request)
    {
        $search = $request->input('query');
        $perPage = $request->input('per_page', 20);
        $query = CoatType::query();

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $coatTypes = $query->orderBy('name')->paginate($perPage);

        return response()->json($coatTypes);
    }

    public function listPets(Request $request)
    {
        $weightRanges = WeightRange::all();
        $pets = PetProfile::where('user_id', Auth::id())->with('certificates')->with('vaccinations')->with('breed')->with('color')->with('coatType')->get();
        foreach ($pets as $pet) {
            $pet->pet_img_url = empty($pet->pet_img) ? '' : asset('storage/pets/' . $pet->pet_img);
            foreach ($weightRanges as $weightRange) {
                $clean = preg_replace('/[^A-Za-z0-9 ]/', '', $weightRange->name);
                $clean = strtolower($clean);
                $clean = trim($clean);
                if ($clean === $pet->size) {
                    $pet->sizeId = $weightRange->id;
                    break;
                }
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Fetched pets successfully',
            'result' => [
                'pets' => $pets,
                'weight_ranges' => $weightRanges
            ]
        ], 200);
    }

    public function addPet(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'breed' => 'required',
            'color' => 'required',
            'coat_type' => 'required',
            'weight' => 'required|numeric',
            'size' => 'required|exists:weight_ranges,id',
            'veterinarian_name' => 'required|string',
            'veterinarian_phone' => 'required|string',
            'spay_neuter' => 'nullable|in:spayed,neutered,intact',
            'pet_img' => 'image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
        ]);

        $birthdate = $request->birthdate ? Carbon::parse($request->birthdate)->format('Y-m-d') : null;

        $pet = new PetProfile;
        $pet->user_id = Auth::id();
        $pet->name = $request->name;
        $pet->birthdate = $birthdate;
        $pet->age = $request->age;
        $pet->breed_id = $request->breed;
        $pet->color_id = $request->color;
        $pet->coat_type_id = $request->coat_type;
        $pet->size = $this->getPetSize($request->size);
        $pet->weight = $request->weight;
        $pet->veterinarian_name = $request->veterinarian_name;
        $pet->veterinarian_phone = $request->veterinarian_phone;
        $pet->notes = $request->notes;
        $pet->vaccine_status = 'missing';
        $pet->spay_neuter = $request->spay_neuter;

        if ($request->hasFile('pet_img')) {
            $path = $request->pet_img->store('public/pets');
            $paths = explode("/", $path);
            $pet->pet_img = end($paths);
        }

        $pet->save();

        return response()->json([
            'status' => true,
            'message' => 'Added new pet successfully',
            'result' => $pet->id
        ], 200);
    }

    public function updatePet(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:pet_profiles,id',
            'name' => 'required|string',
            'breed' => 'required|string',
            'color' => 'required|string',
            'coat_type' => 'required',
            'weight' => 'required|numeric',
            'size' => 'required|exists:weight_ranges,id',
            'veterinarian_name' => 'required|string',
            'veterinarian_phone' => 'required|string',
            'spay_neuter' => 'nullable|in:spayed,neutered,intact',
            'pet_img_action' => 'required|in:keep,change,delete',
            'pet_img' => 'image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
        ]);

        $pet = PetProfile::find($request->id);
        if (!$pet) {
            return response()->json([
                'status' => false,
                'message' => 'Pet not found'
            ], 200);
        }

        $birthdate = $request->birthdate ? Carbon::parse($request->birthdate)->format('Y-m-d') : null;

        $pet->name = $request->name;
        $pet->birthdate = $birthdate;
        $pet->age = $request->age;
        $pet->breed_id = $request->breed;
        $pet->color_id = $request->color;
        $pet->coat_type_id = $request->coat_type;
        $pet->weight = $request->weight;
        $pet->size = $this->getPetSize($request->size);
        $pet->veterinarian_name = $request->veterinarian_name;
        $pet->veterinarian_phone = $request->veterinarian_phone;
        $pet->notes = $request->notes;
        $pet->spay_neuter = $request->spay_neuter;

        // Handle pet image based on action
        switch ($request->pet_img_action) {
            case 'change':
                if ($request->hasFile('pet_img')) {
                    if (!empty($pet->pet_img)) {
                        Storage::delete('public/pets/' . $pet->pet_img);
                    }

                    $path = $request->pet_img->store('public/pets');
                    $paths = explode("/", $path);
                    $pet->pet_img = end($paths);
                }
                break;
            case 'delete':
                if (!empty($pet->pet_img)) {
                    Storage::delete('public/pets/' . $pet->pet_img);
                }
                $pet->pet_img = null;
                break;
        }

        $pet->save();

        return response()->json([
            'status' => true,
            'message' => 'Updated pet successfully',
            'result' => $pet->id
        ], 200);
    }

    public function deletePet(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:pet_profiles,id',
        ]);

        $pet = PetProfile::find($request->id);
        if (empty($pet)) {
            return response()->json([
                'status' => false,
                'message' => 'Pet not found'
            ], 200);
        }

        // delete the pet image
        if ($pet && $pet->pet_img) {
            Storage::delete('public/pets/' . $pet->pet_img);
        }

        // delete the pet certificate file
        PetCertificate::where('pet_profile_id', $pet->id)->each(function ($certificate) {
            if ($certificate->file_path) {
                Storage::delete('public/pets/' . $certificate->file_path);
            }
        });

        $pet->delete();

        return response()->json([
            'status' => true,
            'message' => 'Deleted pet successfully'
        ], 200);
    }

    public function addPetCertificate(Request $request)
    {
        $request->validate([
            'pet_profile_id' => 'required|exists:pet_profiles,id',
            'file' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10000',
        ]);

        $file = $request->file('file');
        $path = $file->store('public/pets');
        $paths = explode("/", $path);

        $certificate = new PetCertificate;
        $certificate->pet_profile_id = $request->pet_profile_id;
        $certificate->file_path = end($paths);
        $certificate->file_name = $file->getClientOriginalName();
        $certificate->file_type = $file->getClientMimeType();
        $certificate->file_size = $file->getSize();
        $certificate->save();

        return response()->json([
            'status' => true,
            'message' => 'Added new pet certificate successfully',
            'result' => $certificate
        ], 200);
    }

    public function addPetVaccinations(Request $request)
    {
        $request->validate([
            'pet_profile_id' => 'required|exists:pet_profiles,id',
            'vaccinations' => 'array',
            'vaccinations.*.type' => 'required|string',
            'vaccinations.*.date' => 'required|string',
        ]);

        foreach ($request->vaccinations as $vaccination) {
            $vacc = new PetVaccination;
            $vacc->pet_profile_id = $request->pet_profile_id;
            $vacc->type = $vaccination['type'];
            $vacc->date = Carbon::parse($vaccination['date'])->format('Y-m-d');
            $vacc->months = $vaccination['months'] ?? null;
            $vacc->save();
        }

        $pet = PetProfile::find($request->pet_profile_id);
        $pet->vaccine_status = "submitted";
        $pet->save();

        // Notify admin with 'send/receive messages' permission about new vaccinations submitted
        $users = User::select('users.*')
            ->join('role_user', 'users.id', '=', 'role_user.user_id')
            ->join('roles_permissions', 'role_user.role_id', '=', 'roles_permissions.role_id')
            ->where('roles_permissions.permission_id', 15)
            ->where('roles_permissions.can_read', 1)
            ->distinct()
            ->get();
        foreach ($users as $user) {
            $notification = new Notification;
            $notification->user_id = $user->id;
            $notification->sender_id = Auth::id();
            $notification->title = "New Pet Vaccinations Submitted";
            $notification->message = "A customer has submitted new pet vaccinations.";
            $notification->type = "pet_vaccination";
            $notification->metadata = json_encode(['pet_id' => $pet->id]);
            $notification->is_read = false;
            $notification->save();
        }

        return response()->json([
            'status' => true,
            'message' => 'Added new pet vaccinations successfully',
            'result' => null
        ], 200);
    }

    private function getPetSize($weightRangeId)
    {
        $weightRange = WeightRange::find($weightRangeId);
        if (!$weightRange) {
            return 'medium'; // default size if not found
        }

        // remove hyphens (and other unwanted special chars if needed) and convert to lowercase
        $clean = preg_replace('/[^A-Za-z0-9 ]/', '', $weightRange->name);
        $clean = strtolower($clean);
        $clean = trim($clean);

        return $clean;
    }
}
