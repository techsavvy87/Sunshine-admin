<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;
use App\Models\PetProfile;
use App\Models\User;
use App\Models\PetVaccination;
use App\Models\PetCertificate;
use App\Models\Breed;
use App\Models\Color;
use App\Models\CoatType;
use App\Models\WeightRange;
use App\Models\Questionnaire;
use App\Models\ServiceCategory;
use App\Models\Service;
use App\Models\PetInitialTemperament;
use App\Models\Appointment;
use App\Models\PetBehavior;

class PetController extends Controller
{
    public function listPets(Request $request)
    {
        $perPage = $request->get('per_page', 20);
        $search = $request->get('search');
        if (!empty($search)) {
            $pets = PetProfile::where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('birthdate', 'like', "%{$search}%")
                    ->orWhere('age', 'like', "%{$search}%")
                    ->orWhereHas('owner.profile', function ($q) use ($search) {
                        $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('phone_number_1', 'like', "%{$search}%")
                            ->orWhere('phone_number_2', 'like', "%{$search}%");
                    });
            })->orderBy('created_at', 'desc')->paginate($perPage);
        } else {
            $pets = PetProfile::orderBy('created_at', 'desc')->paginate($perPage);
        }

        $behaviorIds = $pets->getCollection()
            ->flatMap(function ($pet) {
                $value = $pet->pet_behavior_id ?? [];
                return is_array($value) ? $value : [$value];
            })
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $behaviorMap = PetBehavior::with('icon')->whereIn('id', $behaviorIds)->get()->keyBy('id');

        return view('pets.index', compact('pets', 'search', 'perPage', 'behaviorMap'));
    }

    public function addPet()
    {
        $weightRanges = WeightRange::all();
        return view('pets.create', compact('weightRanges'));
    }

    public function getPetOwners(Request $request)
    {
        $search = $request->get('q');
        $page = $request->get('page');
        $perPage = 6;

        $owners = User::whereHas('roles', function ($query) {
            $query->where('title', 'customer');
        })->where(function ($query) use ($search) {
            $query->where('email', 'like', "%{$search}%")
                ->orWhereHas('profile', function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
        })->with('profile')->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'items' => $owners->items(),
            'has_more' => $owners->hasMorePages(),
        ]);
    }

    public function searchPets(Request $request)
    {
        $search = $request->get('q');
        $page = $request->get('page');
        $perPage = 4;

        $pets = PetProfile::where('name', 'like', "%{$search}%")
            ->orWhereHas('owner.profile', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            })
            ->with('owner.profile')->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'items' => $pets->items(),
            'has_more' => $pets->hasMorePages(),
        ]);
    }

    public function getPetBreeds(Request $request)
    {
        $search = $request->get('q');
        $page = $request->get('page');
        $perPage = 6;

        $breeds = Breed::where('name', 'like', "%{$search}%")->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'items' => $breeds->items(),
            'has_more' => $breeds->hasMorePages(),
        ]);
    }

    public function getPetColors(Request $request)
    {
        $search = $request->get('q');
        $page = $request->get('page');
        $perPage = 6;

        $colors = Color::where('name', 'like', "%{$search}%")->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'items' => $colors->items(),
            'has_more' => $colors->hasMorePages(),
        ]);
    }

    public function getCoatTypes(Request $request)
    {
        $search = $request->get('q');
        $page = $request->get('page');
        $perPage = 6;

        $coatTypes = CoatType::where('name', 'like', "%{$search}%")->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'items' => $coatTypes->items(),
            'has_more' => $coatTypes->hasMorePages(),
        ]);
    }

    public function processFileUpload(Request $request)
    {
        try {
            $request->validate([
                'pet_img' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // 2MB max
            ]);

            // Handle file upload logic here
            $file = $request->file('pet_img');

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

    public function createPet(Request $request)
    {
        $request->validate([
            'pet_name' => 'required|string',
            'sex' => 'required|in:male,female',
            'type' => 'required|in:Dog,Cat',
            'breed' => 'required|string',
            'size' => 'required|exists:weight_ranges,id',
            'weight' => 'required|numeric|min:0',
            'color' => 'required|string',
            'coat_type' => 'required|string',
            'owner' => 'required|exists:users,id',
            'veterinarian_name' => 'required|string',
            'veterinarian_phone' => 'required|string',
            'temp_file' => 'nullable|string',
            'vaccine_status' => 'required|in:missing,submitted,approved,declined,expired',
            'spay_neuter' => 'nullable|in:spayed,neutered,intact',
            'rating' => 'nullable|in:green,yellow,red',
            'rating_notes' => 'nullable|string',
        ]);

        // Create pet profile
        $pet = new PetProfile;
        $pet->user_id = $request->owner;
        $pet->name = $request->pet_name;
        $pet->sex = $request->sex;
        $pet->type = $request->type;
        if ($request->filled('birth_date'))
            $pet->birthdate = Carbon::parse($request->birth_date);
        if ($request->filled('age'))
            $pet->age = $request->age;
        $pet->breed_id = $request->breed;
        $pet->size = $this->getPetSize($request->size);
        $pet->weight = $request->weight;
        $pet->color_id = $request->color;
        $pet->coat_type_id = $request->coat_type;
        $pet->veterinarian_name = $request->veterinarian_name;
        $pet->veterinarian_phone = $request->veterinarian_phone;
        $pet->notes = $request->notes;
        $pet->vaccine_status = $request->vaccine_status;
        $pet->rating = $request->rating;
        $pet->rating_notes = $request->rating_notes;
        $pet->spay_neuter = $request->spay_neuter;

        if ($request->filled('temp_file')) {
            $tempFile = $request->temp_file;
            $tempPath = 'temp/' . $tempFile;

            if (Storage::disk('local')->exists($tempPath)) {
                // Get file contents and ensure it's not null
                $fileContents = Storage::disk('local')->get($tempPath);

                if ($fileContents !== null) {
                    // Move the file to a permanent location
                    $permanentPath = 'pets/' . $tempFile;
                    Storage::disk('public')->put($permanentPath, $fileContents);
                    Storage::disk('local')->delete($tempPath); // Delete the temporary file
                }
            }
            $pet->pet_img = $tempFile; // Store the file name in the pet profile
        }

        $pet->save();

        // Create vaccinations
        $vaccinations = json_decode($request->vaccinations);
        foreach($vaccinations as $vaccine)
        {
            if (!empty($vaccine->type) && !empty($vaccine->date))
            {
                $vaccination = new PetVaccination;
                $vaccination->pet_profile_id = $pet->id;
                $vaccination->type = strtolower($vaccine->type);
                $vaccination->date = $vaccine->date;
                $vaccination->months = $vaccine->months ?? null;
                $vaccination->save();
            }
        }

        // save the uploaded certificates and create pet certificates records if they are existing
        $certificateFiles = $request->file('certificate_files');
        if ($certificateFiles) {
            foreach ($certificateFiles as $file) {
                $path = $file->store('public/pets');
                $paths = explode("/", $path);

                $certificate = new PetCertificate;
                $certificate->pet_profile_id = $pet->id;
                $certificate->file_path = end($paths);
                $certificate->file_name = $file->getClientOriginalName();
                $certificate->file_type = $file->getClientMimeType();
                $certificate->file_size = $file->getSize();
                $certificate->save();
            }
        }

        // Optionally, redirect or return a response
        return redirect()->route('pets')->with([
            'status' => 'success',
            'message' => 'Pet created successfully!'
        ]);
    }

    public function editPet(Request $request, $id)
    {
        $questionnaireId = $request->get('questionnaire_id');
        $target = $request->get('target');

        $pet = PetProfile::findOrFail($id);
        $weightRanges = WeightRange::all();
        // get the selected weight range id based on the pet size
        foreach ($weightRanges as $weightRange) {
            $clean = preg_replace('/[^A-Za-z0-9 ]/', '', $weightRange->name);
            $clean = strtolower($clean);
            $clean = trim($clean);
            if ($clean === $pet->size) {
                $pet->sizeId = $weightRange->id;
                break;
            }
        }
        $serviceCategories = ServiceCategory::all();
        $questionnaires = Questionnaire::where('pet_id', $pet->id)->get();
        $initialTemperament = PetInitialTemperament::where('pet_id', $pet->id)->first();

        // Previous note: one tab per Service Dashboard sidebar service; content = last appointment detail (archive style) or "no appointment"
        $sidebarServices = Service::where('status', 'active')->where('level', 'primary')->orderBy('name')->get();
        $lastAppointmentByService = Appointment::where('pet_id', $pet->id)
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->unique('service_id')
            ->keyBy('service_id');
        $previousNoteTabs = [];
        foreach ($sidebarServices as $service) {
            $lastAppointment = $lastAppointmentByService->get($service->id);
            $previousNoteTabs[] = [
                'type' => 'appointment',
                'label' => $service->name,
                'id' => 'service-' . $service->id,
                'appointment_id' => $lastAppointment ? $lastAppointment->id : null,
            ];
        }

        $petBehaviors = PetBehavior::all();

        return view('pets.update', compact('pet', 'weightRanges', 'serviceCategories', 'questionnaires', 'questionnaireId', 'target', 'initialTemperament', 'previousNoteTabs', 'petBehaviors'));
    }

    public function updatePet(Request $request)
    {
        $request->validate([
            'pet_profile_id' => 'required|exists:pet_profiles,id',
            'pet_name' => 'required|string',
            'sex' => 'required|in:male,female',
            'type' => 'required|in:Dog,Cat',
            'breed' => 'required|string',
            'size' => 'required|exists:weight_ranges,id',
            'weight' => 'required|numeric|min:0',
            'color' => 'required|string',
            'coat_type' => 'required|string',
            'pet_behavior_id' => 'nullable|array',
            'pet_behavior_id.*' => 'exists:pet_behaviors,id',
            'owner' => 'required|exists:users,id',
            'veterinarian_name' => 'required|string',
            'veterinarian_phone' => 'required|string',
            'img_action' => 'required|in:keep,change,delete',
            'temp_file' => 'nullable|string',
            'current_img' => 'nullable|string',
            'vaccine_status' => 'required|in:missing,submitted,approved,declined,expired',
            'spay_neuter' => 'nullable|in:spayed,neutered,intact',
            'rating' => 'nullable|in:green,yellow,red',
            'rating_notes' => 'nullable|string',
        ]);

        // Find existing pet profile
        $pet = PetProfile::findOrFail($request->pet_profile_id);
        $pet->user_id = $request->owner;
        $pet->name = $request->pet_name;
        $pet->sex = $request->sex;
        $pet->type = $request->type;
        if ($request->filled('birth_date'))
            $pet->birthdate = Carbon::parse($request->birth_date);
        else
            $pet->birthdate = null;
        if ($request->filled('age'))
            $pet->age = $request->age;
        else
            $pet->age = null;
        $pet->breed_id = $request->breed;
        $pet->size = $this->getPetSize($request->size);
        $pet->weight = $request->weight;
        $pet->color_id = $request->color;
        $pet->coat_type_id = $request->coat_type;

        $behaviorIds = collect($request->input('pet_behavior_id', []))
            ->filter(function ($id) {
                return !empty($id);
            })
            ->map(function ($id) {
                return (int) $id;
            })
            ->unique()
            ->values();

        $pet->pet_behavior_id = $behaviorIds->all();

        $pet->veterinarian_name = $request->veterinarian_name;
        $pet->veterinarian_phone = $request->veterinarian_phone;
        $pet->notes = $request->notes;
        $pet->vaccine_status = $request->vaccine_status;
        $pet->rating = $request->rating;
        $pet->rating_notes = $request->rating_notes;
        $pet->spay_neuter = $request->spay_neuter;

        // Handle pet img based on action
        switch ($request->img_action) {
            case 'keep':
                // Do nothing - keep the current img
                break;

            case 'change':
                // Delete old img if exists
                if ($pet->pet_img) {
                    $oldImgPath = 'pets/' . $pet->pet_img;
                    if (Storage::disk('public')->exists($oldImgPath)) {
                        Storage::disk('public')->delete($oldImgPath);
                    }
                }

                // Move new img from temp to permanent location
                if ($request->temp_file) {
                    $tempFile = $request->temp_file;
                    $tempPath = 'temp/' . $tempFile;

                    if (Storage::disk('local')->exists($tempPath)) {
                        $permanentPath = 'pets/' . $tempFile;
                        Storage::disk('public')->put($permanentPath, Storage::disk('local')->get($tempPath));
                        Storage::disk('local')->delete($tempPath);
                        $pet->pet_img = $tempFile;
                    }
                }
                break;

            case 'delete':
                // Delete current pet img
                if ($pet->pet_img) {
                    $imgPath = 'pets/' . $pet->pet_img;
                    if (Storage::disk('public')->exists($imgPath)) {
                        Storage::disk('public')->delete($imgPath);
                    }
                    $pet->pet_img = null;
                }
                break;
        }

        $pet->save();

        // save pet vaccinations
        $vaccinations = json_decode($request->vaccinations, true);

        // collect Ids of submitted vaccinations
        $updatedVaccinationIds = [];
        foreach($vaccinations as $vaccination) {
            if (!empty($vaccination['id'])) {
                $updatedVaccinationIds[] = $vaccination['id'];
            }
        }

        // Delete vaccinations that are NOT in the submitted list
        PetVaccination::where('pet_profile_id', $pet->id)
            ->whereNotIn('id', $updatedVaccinationIds)
            ->delete();

        // Update or create attributes
        foreach ($vaccinations as $vaccination) {
            $existingVaccination = PetVaccination::find($vaccination['id']);
            if ($existingVaccination) {
                $existingVaccination->type = strtolower($vaccination['type']);
                $existingVaccination->date = $vaccination['date'];
                $existingVaccination->months = $vaccination['months'] ?? null;
                $existingVaccination->save();
            } else {
                $newVaccination = new PetVaccination;
                $newVaccination->pet_profile_id = $pet->id;
                $newVaccination->type = strtolower($vaccination['type']);
                $newVaccination->date = $vaccination['date'];
                $newVaccination->months = $vaccination['months'] ?? null;
                $newVaccination->save();
            }
        }

        // Delete certificates that are not in the submitted ids
        $certificateIdsStr = $request->certificate_ids;
        $certificateIds = $certificateIdsStr ? array_map('trim', explode(',', $certificateIdsStr)) : [];

        // Delete the uploaded certificate files
        $certificates = PetCertificate::where('pet_profile_id', $pet->id)
            ->whereNotIn('id', $certificateIds)
            ->get();
        foreach ($certificates as $certificate) {
            $filePath = 'public/pets/' . $certificate->file_path;
            if (Storage::exists($filePath)) {
                Storage::delete($filePath);
            }
            $certificate->delete();
        }

        // save the uploaded certificates and create pet certificates records if they are existing
        $certificateFiles = $request->file('certificate_files');
        if ($certificateFiles) {
            foreach ($certificateFiles as $file) {
                $path = $file->store('public/pets');
                $paths = explode("/", $path);

                $certificate = new PetCertificate;
                $certificate->pet_profile_id = $pet->id;
                $certificate->file_path = end($paths);
                $certificate->file_name = $file->getClientOriginalName();
                $certificate->file_type = $file->getClientMimeType();
                $certificate->file_size = $file->getSize();
                $certificate->save();
            }
        }

        return redirect()->route('pets')->with([
            'status' => 'success',
            'message' => 'Pet updated successfully!'
        ]);
    }

    public function downloadCertificate($id)
    {
        $certificate = PetCertificate::findOrFail($id);
        $filePath = 'public/pets/' . $certificate->file_path;
        if (!Storage::exists($filePath)) {
            abort(404);
        }
        return Storage::download($filePath, $certificate->file_name);
    }

    public function deletePet(Request $request)
    {
        $request->validate([
            'pet_id' => 'required|exists:pet_profiles,id',
        ]);

        $pet = PetProfile::findOrFail($request->pet_id);

        // Delete pet img if exists
        if ($pet->pet_img) {
            $imgPath = 'pets/' . $pet->pet_img;
            if (Storage::disk('public')->exists($imgPath)) {
                Storage::disk('public')->delete($imgPath);
            }
        }

        // Delete associated certificates and their files
        $certificates = PetCertificate::where('pet_profile_id', $pet->id)->get();
        foreach ($certificates as $certificate) {
            $filePath = 'public/pets/' . $certificate->file_path;
            if (Storage::exists($filePath)) {
                Storage::delete($filePath);
            }
            $certificate->delete();
        }

        // Finally, delete the pet profile
        $pet->delete();

        return redirect()->route('pets')->with([
            'status' => 'success',
            'message' => 'Pet deleted successfully!'
        ]);
    }

    public function getPets(Request $request)
    {
        $search = $request->get('q', '');

        $pets = PetProfile::where('name', 'like', "%{$search}%")->limit(6)->get();

        return response()->json($pets);
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

    public function saveQuestionnaire(Request $request)
    {
        $request->validate([
            'questionnaire_id' => 'nullable',
            'pet_id' => 'required|exists:pet_profiles,id',
            'service_category_id' => 'required|exists:service_categories,id',
            'questions_answers' => 'required',
            'status' => 'required|in:pending,approved,rejected',
        ]);

        $pet = PetProfile::find($request->pet_id);

        $questionnaireId = $request->questionnaire_id;
        if (isset($questionnaireId)) {
            $questionnaire = Questionnaire::find($questionnaireId);
        } else {
            $questionnaire = new Questionnaire;
            $questionnaire->pet_id = $request->pet_id;
            $questionnaire->user_id = $pet->owner->id;
            $questionnaire->service_category_id = $request->service_category_id;
        }
        $questionnaire->questions_answers = $request->questions_answers;
        $questionnaire->status = $request->status;
        $questionnaire->save();

        return response()->json([
            'status' => true,
            'message' => 'Questionnaire saved successfully.',
            'result' => $questionnaire
        ], 200);
    }

    public function saveInitialTemperament(Request $request)
    {
        $request->validate([
            'temperament_id' => 'nullable|exists:pet_initial_temperaments,id',
            'pet_id' => 'required|exists:pet_profiles,id',
            'temperament_data' => 'required|string',
        ]);

        $temperamentData = json_decode($request->temperament_data, true);
        
        if (!$temperamentData) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid temperament data format.'
            ], 422);
        }

        $temperamentId = $request->temperament_id;
        if ($temperamentId) {
            $initialTemperament = PetInitialTemperament::find($temperamentId);
            if (!$initialTemperament) {
                return response()->json([
                    'status' => false,
                    'message' => 'Initial temperament record not found.'
                ], 404);
            }
        } else {
            $initialTemperament = new PetInitialTemperament();
            $initialTemperament->pet_id = $request->pet_id;
        }
        
        $initialTemperament->temperament_data = $temperamentData;
        $initialTemperament->save();

        return response()->json([
            'status' => true,
            'message' => 'Initial Temperament Assessment saved successfully.',
            'result' => $initialTemperament
        ], 200);
    }
}
