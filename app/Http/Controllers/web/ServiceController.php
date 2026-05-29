<?php

namespace App\Http\Controllers\web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;
use App\Models\ServiceCategory;
use App\Models\Service;
use App\Models\GroupClass;
use App\Models\Package;
use Illuminate\Support\Facades\Http;

class ServiceController extends Controller
{
    public function listCategories(Request $request)
    {
        $categories = ServiceCategory::all();
        return view('services.category', compact('categories'));
    }

    public function createCategory(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $category = new ServiceCategory();
        $category->name = $request->name;
        $category->description = $request->description;
        $category->save();

        return response()->json([
            'message' => 'Service category created successfully.',
            'result' => ServiceCategory::all()
        ]);
    }

    public function updateCategory(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:service_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $category = ServiceCategory::find($request->category_id);
        $category->name = $request->name;
        $category->description = $request->description;
        $category->save();

        return response()->json([
            'message' => 'Service category updated successfully.',
            'result' => ServiceCategory::all()
        ]);
    }

    public function deleteCategory(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:service_categories,id',
        ]);

        $category = ServiceCategory::find($request->id);
        $category->delete();

        return response()->json([
            'message' => 'Service category deleted successfully.',
            'result' => ServiceCategory::all()
        ]);
    }

    public function listServices(Request $request)
    {
        $search = $request->input('search');
        $services = Service::when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })->get();
        return view('services.index', compact('services', 'search'));
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

    public function addService()
    {
        $categories = ServiceCategory::all();
        return view('services.create', compact('categories'));
    }

    public function createService(Request $request)
    {
        $request->validate([
            'service_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|exists:service_categories,id',
            'level' => 'required|in:primary,secondary',
            'temp_file' => 'nullable|string',
        ]);

        $categoryId = $request->category;
        $category = ServiceCategory::find($categoryId);

        $service = new Service;
        $service->name = $request->service_name;
        $service->description = $request->description;
        $service->category_id = $categoryId;
        $service->level = $request->level;
        $service->status = $request->boolean('status') ? 'active' : 'inactive';
        $service->is_double_coated = $request->boolean('coat_type_price');
        $service->coat_type_price = $request->coat_type_price_value;

        if (isGroomingService($service)) {
            $isMultiple = $request->boolean('multi_price_toggle');

            if ($isMultiple) {
                $service->price_small = $request->price_small;
                $service->price_medium = $request->price_medium;
                $service->price_large = $request->price_large;
                $service->price_xlarge = $request->price_xlarge;

                $service->duration_small = $request->duration_small;
                $service->duration_medium = $request->duration_medium;
                $service->duration_large = $request->duration_large;
                $service->duration_xlarge = $request->duration_xlarge;
            } else {
                $service->price = $request->price;
                $service->duration = $request->duration;
            }
        } else if (isDaycareService($service)) {
            $daycarePrice = $request->input('price_daycare');
            $daycareDuration = $request->input('duration_daycare');

            $service->price = $daycarePrice;
            $service->duration = $daycareDuration;

            // Mirror daycare values to size-based columns for backward compatibility in existing flows.
            $service->price_small = $daycarePrice;
            $service->price_medium = $daycarePrice;
            $service->duration_small = $daycareDuration;
            $service->duration_medium = $daycareDuration;
        } else if (isPrivateTrainingService($service)) {
            $service->price_small = $request->price_half_training;
            $service->price_medium = $request->price_one_training;
            $service->price_large = $request->price_travel_training;

            $service->duration_small = 0.5;
            $service->duration_medium = 1;
        } else if (isGroupClassService($service) || isAlaCarteService($service) || isBoardingService($service)) {
            $service->price = $request->price;
            $service->duration = $request->duration;
        } else if (isChauffeurService($service)) {
            $service->price_per_mile = $request->price_per_mile;
        }

        if (isset($request->service_icon)) {
            // save the image file
            $path = $request->service_icon->store('public/services');
            $paths = explode("/", $path);
            $service->icon = end($paths);
        }

        if ($request->filled('temp_file')) {
            $tempFile = $request->temp_file;
            $tempPath = 'temp/' . $tempFile;

            if (Storage::disk('local')->exists($tempPath)) {
                // Get file contents and ensure it's not null
                $fileContents = Storage::disk('local')->get($tempPath);

                if ($fileContents !== null) {
                    // Move the file to a permanent location
                    $permanentPath = 'services/' . $tempFile;
                    Storage::disk('public')->put($permanentPath, $fileContents);
                    Storage::disk('local')->delete($tempPath); // Delete the temporary file
                }
            }
            $service->avatar_img = $tempFile; // Store the file name in the service
        }

        $service->save();

        return redirect()->route('services')->with([
            'status' => 'success',
            'message' => 'Service created successfully!'
        ]);
    }

    public function editService($id)
    {
        $service = Service::findOrFail($id);
        $categories = ServiceCategory::all();
        $categoryText = $service->category ? Str::lower($service->category->name) : '';
        return view('services.update', compact('service', 'categories', 'categoryText'));
    }

    public function updateService(Request $request)
    {
        $request->merge([
            'enable_future_price' => $request->boolean('enable_future_price'),
        ]);

        // Build validation rules based on whether future pricing is enabled
        $validationRules = [
            'service_id' => 'required|exists:services,id',
            'service_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|exists:service_categories,id',
            'level' => 'required|in:primary,secondary',
            'avatar_action' => 'required|in:keep,change,delete',
            'temp_file' => 'nullable|string',
            'current_avatar' => 'nullable|string',
            'enable_future_price' => 'nullable|boolean',
            'future_price' => 'nullable|numeric|min:0',
            'future_price_effective_date' => 'nullable|date|after:today',
        ];

        // If future pricing is enabled, require both fields
        if ($request->boolean('enable_future_price')) {
            $validationRules['future_price'] = 'required|numeric|min:0';
            $validationRules['future_price_effective_date'] = 'required|date|after:today';
        }

        $request->validate($validationRules);

        $categoryId = $request->category;
        $category = ServiceCategory::find($categoryId);

        $service = Service::find($request->service_id);
        $service->name = $request->service_name;
        $service->description = $request->description;
        $service->category_id = $categoryId;
        $service->level = $request->level;
        $service->status = $request->boolean('status') ? 'active' : 'inactive';
        $service->is_double_coated = $request->boolean('coat_type_price');
        $service->coat_type_price = $request->coat_type_price_value;

        $service->price = null;
        $service->price_small = null;
        $service->price_medium = null;
        $service->price_large = null;
        $service->price_xlarge = null;
        $service->price_per_mile = null;
        $service->duration = null;
        $service->duration_small = null;
        $service->duration_medium = null;
        $service->duration_large = null;
        $service->duration_xlarge = null;

        if (isGroomingService($service)) {
            $isMultiple = $request->boolean('multi_price_toggle');

            if ($isMultiple) {
                $service->price_small = $request->price_small;
                $service->price_medium = $request->price_medium;
                $service->price_large = $request->price_large;
                $service->price_xlarge = $request->price_xlarge;

                $service->duration_small = $request->duration_small;
                $service->duration_medium = $request->duration_medium;
                $service->duration_large = $request->duration_large;
                $service->duration_xlarge = $request->duration_xlarge;
            } else {
                $service->price = $request->price;
                $service->duration = $request->duration;
            }
        } else if (isDaycareService($service)) {
            $daycarePrice = $request->input('price_daycare');
            $daycareDuration = $request->input('duration_daycare');

            $service->price = $daycarePrice;
            $service->duration = $daycareDuration;

            // Mirror daycare values to size-based columns for backward compatibility in existing flows.
            $service->price_small = $daycarePrice;
            $service->price_medium = $daycarePrice;
            $service->duration_small = $daycareDuration;
            $service->duration_medium = $daycareDuration;
        } else if (isPrivateTrainingService($service)) {
            $service->price_small = $request->price_half_training;
            $service->price_medium = $request->price_one_training;
            $service->price_large = $request->price_travel_training;

            $service->duration_small = 0.5;
            $service->duration_medium = 1;
        } else if (isGroupClassService($service) || isAlaCarteService($service) || isBoardingService($service)) {
            $service->price = $request->price;
            $service->duration = $request->duration;
        } else if (isChauffeurService($service)) {
            $service->price_per_mile = $request->price_per_mile;
        }

        // Set future price fields - only if enabled, otherwise clear them
        if ($request->boolean('enable_future_price')) {
            $service->future_price = $request->future_price;
            $service->future_price_effective_date = $request->future_price_effective_date;
        } else {
            $service->future_price = null;
            $service->future_price_effective_date = null;
        }

        if ($request->icon_action === 'delete') {
            // Delete existing icon file if exists
            if ($service->icon && Storage::disk('public')->exists('services/' . $service->icon)) {
                Storage::disk('public')->delete('services/' . $service->icon);
            }
            $service->icon = null; // Remove icon reference from database
        }

        if (isset($request->service_icon)) {
            // Delete existing icon file if exists
            if ($service->icon && Storage::disk('public')->exists('services/' . $service->icon)) {
                Storage::disk('public')->delete('services/' . $service->icon);
            }

            // save the image file
            $path = $request->service_icon->store('public/services');
            $paths = explode("/", $path);
            $service->icon = end($paths);
        }

        // Handle avatar based on action
        switch ($request->avatar_action) {
            case 'keep':
                // Do nothing - keep the current avatar
                break;

            case 'change':
                // Delete old avatar if exists
                if ($service->avatar_img) {
                    $oldAvatarPath = 'services/' . $service->avatar_img;
                    if (Storage::disk('public')->exists($oldAvatarPath)) {
                        Storage::disk('public')->delete($oldAvatarPath);
                    }
                }

                // Move new avatar from temp to permanent location
                if ($request->temp_file) {
                    $tempFile = $request->temp_file;
                    $tempPath = 'temp/' . $tempFile;

                    if (Storage::disk('local')->exists($tempPath)) {
                        $permanentPath = 'services/' . $tempFile;
                        Storage::disk('public')->put($permanentPath, Storage::disk('local')->get($tempPath));
                        Storage::disk('local')->delete($tempPath);
                        $service->avatar_img = $tempFile;
                    }
                }
                break;

            case 'delete':
                // Delete current avatar
                if ($service->avatar_img) {
                    $avatarPath = 'services/' . $service->avatar_img;
                    if (Storage::disk('public')->exists($avatarPath)) {
                        Storage::disk('public')->delete($avatarPath);
                    }
                    $service->avatar_img = null;
                }
                break;
        }

        $service->save();

        return redirect()->route('services')->with([
            'status' => 'success',
            'message' => 'Service updated successfully!'
        ]);
    }

    public function deleteService(Request $request)
    {
        $request->validate([
            'service_id' => 'required|exists:services,id',
        ]);

        $service = Service::find($request->service_id);

        // Delete associated files
        if ($service->icon && Storage::disk('public')->exists('services/' . $service->icon)) {
            Storage::disk('public')->delete('services/' . $service->icon);
        }
        if ($service->avatar_img && Storage::disk('public')->exists('services/' . $service->avatar_img)) {
            Storage::disk('public')->delete('services/' . $service->avatar_img);
        }

        $service->delete();

        return redirect()->route('services')->with([
            'status' => 'success',
            'message' => 'Service deleted successfully!'
        ]);
    }

    public function listGroupClasses(Request $request)
    {
        $perPage = $request->get('per_page', 20);
        $search = $request->input('search');
        $groupClasses = GroupClass::when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })->orderBy('created_at', 'desc')->paginate($perPage);
        return view('groupclasses.index', compact('groupClasses', 'search'));
    }

    public function addGroupClass()
    {
        return view('groupclasses.create');
    }

    public function createGroupClass(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_amount' => 'required|numeric|min:0',
            'duration_unit' => 'required|string|in:days,weeks,months',
            'schedule' => 'required|string|max:255',
            'started_at' => 'required|date',
            'status' => 'nullable',
        ]);

        $groupClass = new GroupClass();
        $groupClass->name = $request->name;
        $groupClass->description = $request->description;
        $groupClass->price = $request->price;
        $groupClass->duration_amount = $request->duration_amount;
        $groupClass->duration_unit = $request->duration_unit;
        $groupClass->schedule = $request->schedule;
        $groupClass->started_at = $request->started_at;
        $groupClass->status = $request->boolean('status') ? 'active' : 'inactive';
        $groupClass->save();

        return redirect()->route('group-classes')->with([
            'status' => 'success',
            'message' => 'Group class created successfully!'
        ]);
    }

    public function editGroupClass($id)
    {
        $groupClass = GroupClass::findOrFail($id);
        $schedules = explode(",", $groupClass->schedule);
        return view('groupclasses.update', compact('groupClass', 'schedules'));
    }

    public function updateGroupClass(Request $request)
    {
        $request->validate([
            'class_id' => 'required|exists:group_classes,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_amount' => 'required|numeric|min:0',
            'duration_unit' => 'required|string|in:days,weeks,months',
            'schedule' => 'required|string|max:255',
            'started_at' => 'required|date',
            'status' => 'nullable',
        ]);

        $groupClass = GroupClass::findOrFail($request->class_id);
        $groupClass->name = $request->name;
        $groupClass->description = $request->description;
        $groupClass->price = $request->price;
        $groupClass->duration_amount = $request->duration_amount;
        $groupClass->duration_unit = $request->duration_unit;
        $groupClass->schedule = $request->schedule;
        $groupClass->started_at = $request->started_at;
        $groupClass->status = $request->boolean('status') ? 'active' : 'inactive';
        $groupClass->save();

        return redirect()->route('group-classes')->with([
            'status' => 'success',
            'message' => 'Group class updated successfully!'
        ]);
    }

    public function deleteGroupClass(Request $request)
    {
        $request->validate([
            'class_id' => 'required|exists:group_classes,id',
        ]);

        $groupClass = GroupClass::findOrFail($request->class_id);
        $groupClass->delete();

        return redirect()->route('group-classes')->with([
            'status' => 'success',
            'message' => 'Group class deleted successfully!'
        ]);
    }

    public function listPackages(Request $request)
    {
        $perPage = $request->get('per_page', 20);
        $search = $request->input('search');
        $packages = Package::when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })->orderBy('created_at', 'asc')->paginate($perPage);
        return view('packages.index', compact('packages', 'search'));
    }

    public function addPackage()
    {
        $services = Service::where('status', 'active')
            ->whereHas('category', function ($query) {
                $query->where('name', 'not like', '%Package%')
                      ->where('name', 'not like', '%A La Carte%');
            })->get();
        return view('packages.create', compact('services'));
    }

    public function createPackage(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'days' => 'nullable|integer|min:0',
            'service_ids' => 'nullable',
            'status' => 'nullable',
            'temp_file' => 'nullable|string',
        ]);

        // Handle service_ids - can be string (comma-separated) or array
        $serviceIds = $request->service_ids;
        if (is_array($serviceIds)) {
            $serviceIds = implode(',', array_filter($serviceIds));
        }

        $package = new Package();
        $package->name = $request->name;
        $package->description = $request->description;
        $package->price = $request->price;
        $package->days = $request->days;
        $package->service_ids = $serviceIds ?: null;
        $package->status = $request->boolean('status') ? 'active' : 'inactive';

        // Handle image upload if provided
        if ($request->filled('temp_file')) {
            $tempFile = $request->temp_file;
            $tempPath = 'temp/' . $tempFile;

            if (Storage::disk('local')->exists($tempPath)) {
                // Get file contents and ensure it's not null
                $fileContents = Storage::disk('local')->get($tempPath);

                if ($fileContents !== null) {
                    // Move the file to a permanent location
                    $permanentPath = 'services/' . $tempFile;
                    Storage::disk('public')->put($permanentPath, $fileContents);
                    Storage::disk('local')->delete($tempPath); // Delete the temporary file
                }
            }
            $package->image = $tempFile; // Store the file name in the package
        }

        $package->save();

        return redirect()->route('packages')->with([
            'status' => 'success',
            'message' => 'Package created successfully!'
        ]);
    }

    public function editPackage($id)
    {
        $package = Package::findOrFail($id);
        $services = Service::where('status', 'active')
            ->whereHas('category', function ($query) {
                $query->where('name', 'not like', '%Package%')
                      ->where('name', 'not like', '%Carte%');
            })->get();
        $selectedServiceIds = $package->service_ids ? explode(',', $package->service_ids) : [];
        return view('packages.update', compact('package', 'services', 'selectedServiceIds'));
    }

    public function updatePackage(Request $request)
    {
        $request->validate([
            'package_id' => 'required|exists:packages,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'days' => 'nullable|integer|min:0',
            'service_ids' => 'nullable',
            'status' => 'nullable',
            'avatar_action' => 'required|in:keep,change,delete',
            'temp_file' => 'nullable|string',
            'current_avatar' => 'nullable|string',
        ]);

        // Handle service_ids - can be string (comma-separated) or array
        $serviceIds = $request->service_ids;
        if (is_array($serviceIds)) {
            $serviceIds = implode(',', array_filter($serviceIds));
        }

        $package = Package::findOrFail($request->package_id);
        $package->name = $request->name;
        $package->description = $request->description;
        $package->price = $request->price;
        $package->days = $request->days;
        $package->service_ids = $serviceIds ?: null;
        $package->status = $request->boolean('status') ? 'active' : 'inactive';

        // Handle image upload if provided
        switch ($request->avatar_action) {
            case 'keep':
                // Do nothing - keep the current image
                break;

            case 'change':
                // Delete old image if exists
                if ($package->image) {
                    $oldAvatarPath = 'services/' . $package->image;
                    if (Storage::disk('public')->exists($oldAvatarPath)) {
                        Storage::disk('public')->delete($oldAvatarPath);
                    }
                }

                // Move new image from temp to permanent location
                if ($request->temp_file) {
                    $tempFile = $request->temp_file;
                    $tempPath = 'temp/' . $tempFile;

                    if (Storage::disk('local')->exists($tempPath)) {
                        $permanentPath = 'services/' . $tempFile;
                        Storage::disk('public')->put($permanentPath, Storage::disk('local')->get($tempPath));
                        Storage::disk('local')->delete($tempPath);
                        $package->image = $tempFile;
                    }
                }
                break;

            case 'delete':
                // Delete current avatar
                if ($package->image) {
                    $avatarPath = 'services/' . $package->image;
                    if (Storage::disk('public')->exists($avatarPath)) {
                        Storage::disk('public')->delete($avatarPath);
                    }
                    $package->image = null;
                }
                break;
        }

        $package->save();

        return redirect()->route('packages')->with([
            'status' => 'success',
            'message' => 'Package updated successfully!'
        ]);
    }

    public function deletePackage(Request $request)
    {
        $request->validate([
            'package_id' => 'required|exists:packages,id',
        ]);

        $package = Package::findOrFail($request->package_id);

        // Delete associated image if exists
        if ($package->image && Storage::disk('public')->exists('services/' . $package->image)) {
            Storage::disk('public')->delete('services/' . $package->image);
        }

        $package->delete();

        return redirect()->route('packages')->with([
            'status' => 'success',
            'message' => 'Package deleted successfully!'
        ]);
    }
}
