<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\web\AuthController;
use App\Http\Controllers\web\DashboardController;
use App\Http\Controllers\web\PermissionController;
use App\Http\Controllers\web\RoleController;
use App\Http\Controllers\web\UserController;
use App\Http\Controllers\web\HolidayController;
use App\Http\Controllers\web\CreditTypeController;
use App\Http\Controllers\web\WeightRangeController;
use App\Http\Controllers\web\InventoryController;
use App\Http\Controllers\web\CustomerController;
use App\Http\Controllers\web\CustomerComplaintController;
use App\Http\Controllers\web\AttendanceController;
use App\Http\Controllers\web\PetController;
use App\Http\Controllers\web\ServiceController;
use App\Http\Controllers\web\TimeSlotController;
use App\Http\Controllers\web\AppointmentController;
use App\Http\Controllers\web\ArchiveController;
use App\Http\Controllers\web\AppointmentAuditLogController;
use App\Http\Controllers\web\ReportController;
use App\Http\Controllers\web\EndOfDayController;
use App\Http\Controllers\web\MaintenanceController;
use App\Http\Controllers\web\NotificationController;
use App\Http\Controllers\web\CapacityController;
use App\Http\Controllers\web\CustomerPackageController;
use App\Http\Controllers\web\HelpController;
use App\Http\Controllers\web\DiscountController;
use App\Http\Controllers\web\PetBehaviorController;
use App\Http\Controllers\web\FacilityAddressController;
use App\Http\Controllers\web\KennelController;
use App\Http\Controllers\web\RoomController;
use App\Http\Controllers\web\PreCheckinController;
use App\Http\Controllers\web\PaymentController;
use App\Http\Controllers\web\AdminPaymentController;
use App\Http\Controllers\web\StripeWebhookController;

Route::controller(PreCheckinController::class)->group(function () {
    Route::get('/pre-checkin/{token}', 'show')->name('pre-checkin.show');
    Route::post('/pre-checkin/{token}', 'save')->name('pre-checkin.save');
});

Route::controller(PaymentController::class)->group(function () {
    Route::get('/payment/{token}', 'showPaymentPage')->name('payment.page');
    Route::post('/payment/create-intent', 'createPaymentIntent')->name('payment.create-intent');
    Route::post('/payment/confirm', 'confirmPayment')->name('payment.confirm');
});

Route::controller(CustomerController::class)->group(function () {
    Route::get('/customer-invite/{token}', 'showInviteRegistrationForm')->name('customer-invite.form');
    Route::post('/customer-invite/{token}', 'submitInviteRegistration')->name('customer-invite.submit');
    Route::post('/customer-invite/customer-file/process', 'processInviteCustomerFileUpload')->name('process-file-customer-invite');
    Route::delete('/customer-invite/customer-file/revert', 'revertInviteCustomerFileUpload')->name('revert-file-customer-invite');
    Route::post('/customer-invite/pet-file/process', 'processInvitePetFileUpload')->name('process-file-pet-invite');
    Route::delete('/customer-invite/pet-file/revert', 'revertInvitePetFileUpload')->name('revert-file-pet-invite');
});

Route::post('/webhook/stripe', [StripeWebhookController::class, 'handle'])->name('stripe.webhook')->withoutMiddleware(['csrf']);

Route::controller(AuthController::class)->group(function () {
    Route::get('/login', 'login')->name('login');
    Route::post('/login/handle', 'handleLogin')->name('login-handle');
    Route::get('/forgot/password', 'forgotPassword')->name('forgot-password');
    Route::post('/forgot/password/verify', 'verifyForgotPassword')->name('verify-forgot-password');
    Route::get('/reset-password/verify', 'verifyResetPassword')->name('verify-reset-password');
    Route::post('/reset/password', 'resetPassword')->name('reset-password');
});

Route::middleware(['auth'])->group(function () {
    Route::controller(AuthController::class)->group(function () {
        Route::get('/logout', 'logout')->name('logout');
    });

    Route::controller(DashboardController::class)->group(function () {
        Route::get('/', 'index')->name('dashboard');
        Route::get('dashboard/service/{id}', 'serviceDashboard')->name('service-dashboard')->middleware('ensure.permission:id,can_read');
        Route::get('dashboard/list/{id}', 'listDashboard')->name('list-dashboard')->middleware('ensure.permission:id,can_read');
        Route::get('dashboard/appointment/{id}', 'appointmentDetail')->name('appointment-dashboard')->middleware('ensure.permission:3,can_read');
        Route::get('dashboard/completed-appointments', 'completedAppointments')->name('completed-appointments')->middleware('ensure.permission:id,can_read');
        Route::get('boarding-process-log', 'boardingProcessLog')->name('boarding-process-log')->middleware('ensure.permission:23,can_read');
        Route::get('boarding-process-log/create', 'createBoardingProcessLog')->name('boarding-process-log-create')->middleware('ensure.permission:23,can_create');
        Route::post('boarding-process-log/save', 'saveBoardingProcessLog')->name('boarding-process-log-save')->middleware('ensure.permission:23,can_create');
        Route::post('boarding-process-log/get-checkin-data', 'getBoardingCheckinData')->name('boarding-process-log-get-checkin-data')->middleware('ensure.permission:23,can_read');
        Route::post('boarding-process-log/treatment-list-yesterday-pet-ids', 'getTreatmentListYesterdayPetIds')->name('boarding-process-log-treatment-list-yesterday-pet-ids')->middleware('ensure.permission:23,can_read');
        Route::get('boarding-process-log/{id}/edit', 'editBoardingProcessLog')->name('boarding-process-log-edit')->middleware('ensure.permission:23,can_update');
        Route::post('boarding-process-log/{id}/update', 'updateBoardingProcessLog')->name('boarding-process-log-update')->middleware('ensure.permission:23,can_update');
        Route::delete('boarding-process-log/{id}/delete', 'deleteBoardingProcessLog')->name('boarding-process-log-delete')->middleware('ensure.permission:23,can_delete');
        Route::get('groomer-calendar/{id}', 'groomerCalendar')->name('groomer-calendar')->middleware('ensure.permission:id,can_read');
        Route::get('groomer-calendar/{id}/data', 'groomerCalendarData')->name('groomer-calendar-data')->middleware('ensure.permission:id,can_read');
        Route::get('/archive/{id}/boarding-detail-report/pdf', 'exportBoardingDetailReportPDF')->name('export-boarding-detail-report-pdf')->middleware('ensure.permission:23,can_read');
    });

    Route::controller(HelpController::class)->group(function () {
        Route::get('/help', 'index')->name('help');
        Route::get('/help/{section}', 'detail')->name('help.detail');
    });

    Route::controller(PermissionController::class)->group(function () {
        Route::get('/permissions', 'list')->name('permissions')->middleware('ensure.permission:6,can_read');
        Route::post('/permission/create', 'create')->name('create-permission')->middleware('ensure.permission:6,can_create');
        Route::post('/permission/update', 'update')->name('update-permission')->middleware('ensure.permission:6,can_update');
        Route::post('/permission/delete', 'delete')->name('delete-permission')->middleware('ensure.permission:6,can_delete');
    });

    Route::controller(RoleController::class)->group(function () {
        Route::get('/roles', 'list')->name('roles')->middleware('ensure.permission:6,can_read');
        Route::post('/role/create', 'create')->name('create-role')->middleware('ensure.permission:6,can_create');
        Route::post('/role/update', 'update')->name('update-role')->middleware('ensure.permission:6,can_update');
        Route::post('/role/delete', 'delete')->name('delete-role')->middleware('ensure.permission:6,can_delete');

        Route::post('/role/permission/create', 'createRolePermission')->name('create-role-permission')->middleware('ensure.permission:6,can_create');
        Route::post('/role/permission/update', 'updateRolePermission')->name('update-role-permission')->middleware('ensure.permission:6,can_update');
        Route::post('/role/permission/delete', 'deleteRolePermission')->name('delete-role-permission')->middleware('ensure.permission:6,can_delete');
    });

    Route::controller(UserController::class)->group(function () {
        Route::get('/users', 'listUsers')->name('users')->middleware('ensure.permission:5,can_read');
        Route::get('/user/add', 'addUser')->name('add-user')->middleware('ensure.permission:5,can_create');
        Route::post('/user/create', 'createUser')->name('create-user')->middleware('ensure.permission:5,can_create');
        Route::get('/user/edit/{id}', 'editUser')->name('edit-user')->middleware('ensure.permission:5,can_update');
        Route::post('/user/update', 'updateUser')->name('update-user')->middleware('ensure.permission:5,can_update');
        Route::post('/user/delete', 'deleteUser')->name('delete-user')->middleware('ensure.permission:5,can_delete');

        Route::post('/file/upload/process', 'processFileUpload')->name('process-file-upload');
        Route::delete('/file/upload/revert', 'revertFileUpload')->name('revert-file-upload');
    });

    Route::controller(HolidayController::class)->group(function () {
        Route::get('/holidays', 'list')->name('holidays')->middleware('ensure.permission:7,can_read');
        Route::get('/holiday/add', 'add')->name('add-holiday')->middleware('ensure.permission:7,can_create');
        Route::post('/holiday/create', 'create')->name('create-holiday')->middleware('ensure.permission:7,can_create');
        Route::get('/holiday/edit/{id}', 'edit')->name('edit-holiday')->middleware('ensure.permission:7,can_update');
        Route::post('/holiday/update', 'update')->name('update-holiday')->middleware('ensure.permission:7,can_update');
        Route::post('/holiday/delete', 'delete')->name('delete-holiday')->middleware('ensure.permission:7,can_delete');
    });

    Route::controller(CreditTypeController::class)->group(function () {
        Route::get('/credit-types', 'list')->name('credit-types')->middleware('ensure.permission:10,can_read');
        Route::post('/credit-type/create', 'create')->name('create-credit-type')->middleware('ensure.permission:10,can_create');
        Route::post('/credit-type/update', 'update')->name('update-credit-type')->middleware('ensure.permission:10,can_update');
        Route::post('/credit-type/delete', 'delete')->name('delete-credit-type')->middleware('ensure.permission:10,can_delete');
    });

    Route::controller(WeightRangeController::class)->group(function () {
        Route::get('/weight-ranges', 'list')->name('weight-ranges')->middleware('ensure.permission:8,can_read');
        Route::post('/weight-range/create', 'create')->name('create-weight-range')->middleware('ensure.permission:8,can_create');
        Route::post('/weight-range/update', 'update')->name('update-weight-range')->middleware('ensure.permission:8,can_update');
        Route::post('/weight-range/delete', 'delete')->name('delete-weight-range')->middleware('ensure.permission:8,can_delete');
    });

    Route::controller(CapacityController::class)->group(function () {
        Route::get('/capacities', 'list')->name('capacities')->middleware('ensure.permission:9,can_read');
        Route::post('/capacity/create', 'create')->name('create-capacity')->middleware('ensure.permission:9,can_create');
        Route::post('/capacity/update', 'update')->name('update-capacity')->middleware('ensure.permission:9,can_update');
        Route::post('/capacity/delete', 'delete')->name('delete-capacity')->middleware('ensure.permission:9,can_delete');
    });

    Route::controller(FacilityAddressController::class)->group(function () {
        Route::get('/facility-address', 'index')->name('facility-address')->middleware('ensure.permission:26,can_read');
        Route::post('/facility-address/update', 'update')->name('update-facility-address')->middleware('ensure.permission:26,can_update');
    });

    Route::controller(InventoryController::class)->group(function () {
        Route::get('/inventory/categories', 'listCategories')->name('inventory-categories');
        Route::post('/inventory/category/create', 'createCategory')->name('create-inventory-category')->middleware('ensure.permission:4,can_create');
        Route::post('/inventory/category/update', 'updateCategory')->name('update-inventory-category')->middleware('ensure.permission:4,can_update');
        Route::post('/inventory/category/delete', 'deleteCategory')->name('delete-inventory-category')->middleware('ensure.permission:4,can_delete');

        Route::get('/inventory/category/parent', 'getParentCategories')->name('get-parent-categories');

        Route::get('/inventory/items', 'listItems')->name('inventory-items')->middleware('ensure.permission:4,can_read');
        Route::get('/inventory/item/add', 'addItem')->name('add-inventory-item')->middleware('ensure.permission:4,can_create');
        Route::post('/inventory/item/create', 'createItem')->name('create-inventory-item')->middleware('ensure.permission:4,can_create');
        Route::get('/inventory/item/edit/{id}', 'editItem')->name('edit-inventory-item')->middleware('ensure.permission:4,can_update');
        Route::post('/inventory/item/update', 'updateItem')->name('update-inventory-item')->middleware('ensure.permission:4,can_update');
        Route::post('/inventory/item/delete', 'deleteItem')->name('delete-inventory-item')->middleware('ensure.permission:4,can_delete');

        Route::get('/inventory/item/detail/{id}', 'detailItem')->name('detail-inventory-item')->middleware('ensure.permission:4,can_read');
        Route::post('/inventory/transaction/create', 'createTransaction')->name('create-inventory-transaction')->middleware('ensure.permission:4,can_create');

        Route::get('/inventory/items/json', 'getInventoryItems')->name('get-inventory-items');
    });

    Route::controller(CustomerController::class)->group(function () {
        Route::get('/customers', 'listCustomers')->name('customers')->middleware('ensure.permission:1,can_read');
        Route::get('/customer/add', 'addCustomer')->name('add-customer')->middleware('ensure.permission:1,can_create');
        Route::post('/customer/create', 'createCustomer')->name('create-customer')->middleware('ensure.permission:1,can_create');
        Route::post('/customer/invite', 'sendInvite')->name('send-customer-invite')->middleware('ensure.permission:1,can_create');
        Route::get('/customer/edit/{id}', 'editCustomer')->name('edit-customer')->middleware('ensure.permission:1,can_update');
        Route::get('/customer/{id}/invoices', 'customerInvoices')->name('customer-invoices')->middleware('ensure.permission:1,can_read');
        Route::post('/customer/update', 'updateCustomer')->name('update-customer')->middleware('ensure.permission:1,can_update');
        Route::post('/customer/delete', 'deleteCustomer')->name('delete-customer')->middleware('ensure.permission:1,can_delete');

        Route::post('/customer/file/process', 'processFileUpload')->name('process-file-customer');
        Route::delete('/customer/file/revert', 'revertFileUpload')->name('revert-file-customer');
    });

    Route::controller(CustomerComplaintController::class)->group(function () {
        Route::get('/complaints', 'listComplaints')->name('complaints')->middleware('ensure.permission:24,can_read');
        Route::post('/complaints', 'createComplaint')->name('create-complaint')->middleware('ensure.permission:24,can_create');
        Route::post('/complaints/update', 'updateComplaint')->name('update-complaint')->middleware('ensure.permission:24,can_update');
        Route::post('/complaints/delete', 'deleteComplaint')->name('delete-complaint')->middleware('ensure.permission:24,can_delete');
    });

    Route::controller(AttendanceController::class)->group(function () {
        Route::get('/attendance', 'listAttendance')->name('attendance')->middleware('ensure.permission:5,can_read');
        Route::post('/attendance/update', 'updateAttendance')->name('update-attendance')->middleware('ensure.permission:5,can_update');
    });

    Route::controller(PetController::class)->group(function () {
        Route::get('/pets', 'listPets')->name('pets')->middleware('ensure.permission:2,can_read');
        Route::get('/pet/add', 'addPet')->name('add-pet')->middleware('ensure.permission:2,can_create');
        Route::post('/pet/create', 'createPet')->name('create-pet')->middleware('ensure.permission:2,can_create');
        Route::get('/pet/edit/{id}', 'editPet')->name('edit-pet')->middleware('ensure.permission:2,can_update');
        Route::post('/pet/update', 'updatePet')->name('update-pet')->middleware('ensure.permission:2,can_update');
        Route::post('/pet/delete', 'deletePet')->name('delete-pet')->middleware('ensure.permission:2,can_delete');

        Route::post('/pet/file/process', 'processFileUpload')->name('process-file-pet');
        Route::delete('/pet/file/revert', 'revertFileUpload')->name('revert-file-pet');

        Route::get('/pet/owners', 'getPetOwners')->name('get-pet-owners');
        Route::get('/pet/breeds', 'getPetBreeds')->name('get-pet-breeds');
        Route::get('/pet/colors', 'getPetColors')->name('get-pet-colors');
        Route::get('/pet/coattypes', 'getCoatTypes')->name('get-pet-coat-types');
        Route::get('/pet/search', 'searchPets');

        Route::get('/pet/certificate/download/{id}', 'downloadCertificate')->name('download-certificate-pet');
        Route::get('/pets/get', 'getPets')->name('get-pets');
        Route::post('/pet/questionnaire/save', 'saveQuestionnaire')->name('save-pet-questionnaire');
        Route::post('/pet/initial-temperament/save', 'saveInitialTemperament')->name('save-pet-initial-temperament');
    });

    Route::controller(ServiceController::class)->group(function () {
        Route::get('/service/categories', 'listCategories')->name('service-categories')->middleware('ensure.permission:11,can_read');
        Route::post('/service/category/create', 'createCategory')->name('create-service-category')->middleware('ensure.permission:11,can_create');
        Route::post('/service/category/update', 'updateCategory')->name('update-service-category')->middleware('ensure.permission:11,can_update');
        Route::post('/service/category/delete', 'deleteCategory')->name('delete-service-category')->middleware('ensure.permission:11,can_delete');

        Route::get('/services', 'listServices')->name('services')->middleware('ensure.permission:12,can_read');
        Route::get('/service/add', 'addService')->name('add-service')->middleware('ensure.permission:12,can_create');
        Route::post('/service/create', 'createService')->name('create-service')->middleware('ensure.permission:12,can_create');
        Route::get('/service/edit/{id}', 'editService')->name('edit-service')->middleware('ensure.permission:12,can_update');
        Route::post('/service/update', 'updateService')->name('update-service')->middleware('ensure.permission:12,can_update');
        Route::post('/service/delete', 'deleteService')->name('delete-service')->middleware('ensure.permission:12,can_delete');

        Route::post('/service/img/process', 'processFileUpload')->name('process-file-service');
        Route::delete('/service/img/revert', 'revertFileUpload')->name('revert-file-service');

        Route::get('/classes', 'listGroupClasses')->name('group-classes')->middleware('ensure.permission:20,can_read');
        Route::get('/class/add', 'addGroupClass')->name('add-group-class')->middleware('ensure.permission:20,can_create');
        Route::post('/class/create', 'createGroupClass')->name('create-group-class')->middleware('ensure.permission:20,can_create');
        Route::get('/class/edit/{id}', 'editGroupClass')->name('edit-group-class')->middleware('ensure.permission:20,can_update');
        Route::post('/class/update', 'updateGroupClass')->name('update-group-class')->middleware('ensure.permission:20,can_update');
        Route::post('/class/delete', 'deleteGroupClass')->name('delete-group-class')->middleware('ensure.permission:20,can_delete');

        Route::get('/packages', 'listPackages')->name('packages')->middleware('ensure.permission:22,can_read');
        Route::get('/package/add', 'addPackage')->name('add-package')->middleware('ensure.permission:22,can_create');
        Route::post('/package/create', 'createPackage')->name('create-package')->middleware('ensure.permission:22,can_create');
        Route::get('/package/edit/{id}', 'editPackage')->name('edit-package')->middleware('ensure.permission:22,can_update');
        Route::post('/package/update', 'updatePackage')->name('update-package')->middleware('ensure.permission:22,can_update');
        Route::post('/package/delete', 'deletePackage')->name('delete-package')->middleware('ensure.permission:22,can_delete');
    });

    Route::controller(CustomerPackageController::class)->group(function () {
        Route::get('/customer-packages', 'index')->name('customer-packages')->middleware('ensure.permission:25,can_read');
        Route::get('/customer-package/add', 'add')->name('add-customer-package')->middleware('ensure.permission:25,can_create');
        Route::post('/customer-package/create', 'create')->name('create-customer-package')->middleware('ensure.permission:25,can_create');
        Route::get('/customer-package/edit/{id}', 'edit')->name('edit-customer-package')->middleware('ensure.permission:25,can_update');
        Route::post('/customer-package/update', 'update')->name('update-customer-package')->middleware('ensure.permission:25,can_update');
        Route::post('/customer-package/delete', 'destroy')->name('delete-customer-package')->middleware('ensure.permission:25,can_delete');
    });

    Route::controller(TimeSlotController::class)->group(function () {
        Route::get('/timeslots', 'listTimeSlots')->name('timeslots')->middleware('ensure.permission:13,can_read');
        Route::get('/timeslot/add', 'addTimeSlot')->name('add-timeslot')->middleware('ensure.permission:13,can_create');
        Route::post('/timeslot/generate', 'generateTimeSlot')->name('generate-timeslot')->middleware('ensure.permission:13,can_create');
        Route::get('/timeslot/holidays', 'getHolidaysInRange')->name('get-holidays-in-range')->middleware('ensure.permission:13,can_read');
        Route::get('/timeslot/existing-dates', 'getExistingTimeSlotDates')->name('get-existing-timeslot-dates')->middleware('ensure.permission:13,can_read');
        Route::post('/timeslot/create', 'createTimeSlot')->name('create-timeslot')->middleware('ensure.permission:13,can_create');
        Route::get('/timeslot/edit/{id}', 'editTimeSlot')->name('edit-timeslot')->middleware('ensure.permission:13,can_update');
        Route::post('/timeslot/update', 'updateTimeSlot')->name('update-timeslot')->middleware('ensure.permission:13,can_update');
        Route::post('/timeslot/delete', 'deleteTimeSlot')->name('delete-timeslot')->middleware('ensure.permission:13,can_delete');
        Route::post('/timeslot/check-overlap', 'checkOverlap')->name('check-timeslot-overlap')->middleware('ensure.permission:13,can_read');
    });

    Route::controller(AppointmentController::class)->group(function () {
        Route::get('/appointments', 'list')->name('appointments')->middleware('ensure.permission:3,can_read');
        Route::get('/appointment/add', 'add')->name('add-appointment')->middleware('ensure.permission:3,can_create');
        Route::post('/appointment/create', 'create')->name('create-appointment')->middleware('ensure.permission:3,can_create');
        Route::post('/appointment/create-package', 'createPackageAppointment')->name('create-package-appointment')->middleware('ensure.permission:3,can_create');
        Route::get('/appointment/generate-invoice-number', 'generateInvoiceNumber')->name('generate-invoice-number')->middleware('ensure.permission:3,can_create');
        Route::get('/appointment/edit/{id}', 'edit')->name('edit-appointment')->middleware('ensure.permission:3,can_update');
        Route::post('/appointment/update', 'update')->name('update-appointment')->middleware('ensure.permission:3,can_update');
        Route::post('/appointment/delete', 'delete')->name('delete-appointment')->middleware('ensure.permission:3,can_delete');
        Route::post('/appointment/pending/confirm', 'confirmPending')->name('confirm-pending-appointment')->middleware('ensure.permission:3,can_update');
        Route::post('/appointment/{id}/checkin/flows', 'updateCheckinFlows')->name('update-checkin-flows')->middleware('ensure.permission:3,can_update');
        Route::post('/appointment/{id}/checkin/confirm', 'confirmCheckedIn')->name('confirm-checked-in-appointment')->middleware('ensure.permission:3,can_update');
        Route::post('/appointment/{id}/process/flows', 'updateProcessFlows')->name('update-process-flows')->middleware('ensure.permission:3,can_update');
        Route::get('/appointment/{id}/process/flows', 'getProcessFlows')->name('get-process-flows')->middleware('ensure.permission:3,can_read');
        Route::post('/appointment/{id}/process/confirm', 'confirmInProgress')->name('confirm-in-progress-appointment')->middleware('ensure.permission:3,can_update');
        Route::post('/appointment/{id}/process/ala-carte', 'saveAlaCarteProcess')->name('save-ala-carte-process')->middleware('ensure.permission:3,can_update');
        Route::post('/appointment/{id}/invoice/save', 'saveInvoice')->name('save-invoice-appointment')->middleware('ensure.permission:3,can_update');
        Route::post('/appointment/{id}/email/send', 'sendCustomerEmail')->name('send-appointment-customer-email')->middleware('ensure.permission:3,can_update');
        Route::post('/appointment/{id}/notify/send', 'sendCustomerNotification')->name('send-appointment-customer-notification')->middleware('ensure.permission:3,can_update');
        Route::post('/appointment/{id}/completed/confirm', 'confirmCompleted')->name('confirm-completed-appointment')->middleware('ensure.permission:3,can_update');
        Route::post('/appointment/{id}/status/update', 'updateStatus')->name('update-appointment-status')->middleware('ensure.permission:3,can_update');

        Route::get('/appointment/customers', 'getCustomers')->name('get-appointment-customers')->middleware('ensure.permission:3,can_read');
        Route::get('/appointment/pets/{customerId}', 'getCustomerPets')->name('get-customer-pets')->middleware('ensure.permission:3,can_read');
        Route::get('/appointment/customer-packages/{customerId}', 'getCustomerPackages')->name('get-appointment-customer-packages')->middleware('ensure.permission:3,can_read');
        Route::get('/appointment/staffs', 'getStaffs')->name('get-appointment-staffs')->middleware('ensure.permission:3,can_read');
        Route::post('/appointment/timeslots', 'getTimeSlots')->name('get-appointment-timeslots')->middleware('ensure.permission:3,can_read');
        Route::get('/appointment/available-kennels', 'getAvailableKennels')->name('get-appointment-available-kennels')->middleware('ensure.permission:3,can_read');

        Route::get('/appointment/view/calendar', 'viewCalendar')->name('view-appointment-calendar')->middleware('ensure.permission:3,can_read');
        Route::post('/appointment/validate', 'getValidationInfo')->name('get-validation-info')->middleware('ensure.permission:3,can_read');
        Route::post('/appointment/validate-assignment', 'validateAssignment')->name('validate-assignment')->middleware('ensure.permission:3,can_read');
    });

    Route::controller(AdminPaymentController::class)->group(function () {
        Route::get('/financials/payments', 'payments')->name('financials.payments');
        Route::get('/financials/payouts', 'payouts')->name('financials.payouts');
        Route::post('/financials/payouts/withdraw', 'withdraw')->name('financials.payouts.withdraw')->middleware('ensure.permission:14,can_create');
    });

    Route::controller(ArchiveController::class)->group(function () {
        Route::get('/archives', 'index')->name('archives')->middleware('ensure.permission:3,can_read');
        Route::get('/archive/{id}', 'detail')->name('archive-detail')->middleware('ensure.permission:3,can_read');
        Route::get('/archive/{id}/report-fragment', 'reportFragment')->name('archive-report-fragment')->middleware('ensure.permission:3,can_read');
        Route::get('/archive/{id}/grooming-report/pdf', 'exportGroomingReportPDF')->name('export-grooming-report-pdf')->middleware('ensure.permission:18,can_read');
        Route::get('/archive/{id}/training-report/pdf', 'exportTrainingReportPDF')->name('export-training-report-pdf')->middleware('ensure.permission:19,can_read');
        Route::get('/archive/{id}/daycare-report/pdf', 'exportDaycareReportPDF')->name('export-daycare-report-pdf')->middleware('ensure.permission:17,can_read');
        Route::get('/archive/{id}/group-class-report/pdf', 'exportGroupClassReportPDF')->name('export-group-class-report-pdf')->middleware('ensure.permission:20,can_read');
        Route::get('/archive/{id}/ala-carte-report/pdf', 'exportAlaCarteReportPDF')->name('export-ala-carte-report-pdf')->middleware('ensure.permission:21,can_read');
        Route::get('/archive/{id}/boarding-report/pdf', 'exportBoardingReportPDF')->name('export-boarding-report-pdf')->middleware('ensure.permission:23,can_read');
        Route::get('/archive/{id}/package-report/pdf', 'exportPackageReportPDF')->name('export-package-report-pdf')->middleware('ensure.permission:22,can_read');
        Route::get('/archive/{id}/concierge-report/pdf', 'exportConciergeReportPDF')->name('export-concierge-report-pdf')->middleware('ensure.permission:3,can_read');
        Route::get('/archive/{id}/concierge-report', 'getConciergeReport')->name('get-concierge-report')->middleware('ensure.permission:3,can_read');
    });

    Route::controller(AppointmentAuditLogController::class)->group(function () {
        Route::get('/appointment-audit-log', 'index')->name('appointment-audit-log')->middleware('ensure.permission:23,can_read');
        Route::post('/appointment-audit-log/delete', 'destroy')->name('appointment-audit-log-delete')->middleware('ensure.permission:23,can_delete');
    });

    Route::controller(ReportController::class)->group(function () {
        Route::get('/incident/{serviceId}/reports', 'listIncidentReports')->name('list-incident-reports')->middleware('ensure.permission:26,can_read');
        Route::get('/incident/{serviceId}/report/add', 'addIncidentReport')->name('add-incident-report')->middleware('ensure.permission:26,can_create');
        Route::post('/incident/report/create', 'createIncidentReport')->name('create-incident-report')->middleware('ensure.permission:26,can_create');
        Route::get('/incident/report/edit/{id}', 'editIncidentReport')->name('edit-incident-report')->middleware('ensure.permission:26,can_update');
        Route::post('/incident/report/update', 'updateIncidentReport')->name('update-incident-report')->middleware('ensure.permission:26,can_update');
        Route::post('/incident/report/delete', 'deleteIncidentReport')->name('delete-incident-report')->middleware('ensure.permission:26,can_delete');
    });

    Route::controller(EndOfDayController::class)->group(function () {
        Route::get('/reports/end-of-day', 'listEndOfDay')->name('end-of-day')->middleware('ensure.permission:23,can_read');
        Route::post('/reports/end-of-day/maintenance', 'createEndOfDayMaintenance')->name('create-end-of-day-maintenance')->middleware('ensure.permission:27,can_create');
    });

    Route::controller(MaintenanceController::class)->group(function () {
        Route::get('/maintenance', 'listMaintenance')->name('maintenance')->middleware('ensure.permission:27,can_read');
        Route::post('/maintenance', 'createMaintenance')->name('create-maintenance')->middleware('ensure.permission:27,can_create');
        Route::post('/maintenance/update', 'updateMaintenance')->name('update-maintenance')->middleware('ensure.permission:27,can_update');
        Route::post('/maintenance/delete', 'deleteMaintenance')->name('delete-maintenance')->middleware('ensure.permission:27,can_delete');
    });

    Route::controller(NotificationController::class)->group(function () {
        Route::get('/notifications/list/user', 'listByUser')->name('list-notification-user')->middleware('ensure.permission:28,can_read');
        Route::get('/notifications', 'list')->name('notifications')->middleware('ensure.permission:28,can_read');
        Route::get('/notification/open/{id}', 'open')->name('open-notification')->middleware('ensure.permission:28,can_update');
        Route::post('/notification/delete', 'delete')->name('delete-notification')->middleware('ensure.permission:28,can_update');
        Route::get('/notification/mark-read/{id}', 'markAsRead')->name('mark-notification-read')->middleware('ensure.permission:28,can_update');
        Route::get('/notification/mark-read', 'markReadUser')->name('mark-notification-read-user')->middleware('ensure.permission:28,can_update');
    });

    Route::controller(DiscountController::class)->group(function () {
        Route::get('/discounts', 'listDiscounts')->name('discounts')->middleware('ensure.permission:24,can_read');
        Route::get('/discount/add', 'addDiscount')->name('add-discount')->middleware('ensure.permission:24,can_create');
        Route::get('/discount/edit/{id}', 'editDiscount')->name('edit-discount')->middleware('ensure.permission:24,can_update');
        Route::post('/discount/create', 'createDiscount')->name('create-discount')->middleware('ensure.permission:24,can_create');
        Route::post('/discount/update', 'updateDiscount')->name('update-discount')->middleware('ensure.permission:24,can_update');
        Route::post('/discount/delete', 'deleteDiscount')->name('delete-discount')->middleware('ensure.permission:24,can_delete');
    });

    Route::controller(PetBehaviorController::class)->group(function () {
        Route::get('/pet-behaviors', 'listBehaviors')->name('pet-behaviors')->middleware('ensure.permission:25,can_read');
        Route::post('/pet-behavior/create', 'create')->name('create-behavior')->middleware('ensure.permission:25,can_create');
        Route::post('/pet-behavior/update', 'update')->name('update-behavior')->middleware('ensure.permission:25,can_update');
        Route::post('/pet-behavior/delete', 'delete')->name('delete-behavior')->middleware('ensure.permission:25,can_delete');
    });

    Route::controller(KennelController::class)->group(function () {
        Route::get('/kennels', 'listKennels')->name('kennels')->middleware('ensure.permission:27,can_read');
        Route::get('/kennel/add', 'addKennel')->name('add-kennel')->middleware('ensure.permission:27,can_create');
        Route::get('/kennel/edit/{id}', 'editKennel')->name('edit-kennel')->middleware('ensure.permission:27,can_update');
        Route::post('/kennel/create', 'createKennel')->name('create-kennel')->middleware('ensure.permission:27,can_create');
        Route::post('/kennel/update', 'updateKennel')->name('update-kennel')->middleware('ensure.permission:27,can_update');
        Route::post('/kennel/delete', 'deleteKennel')->name('delete-kennel')->middleware('ensure.permission:27,can_delete');
        Route::post('/kennel/file/process', 'processFileUpload')->name('process-file-kennel');
        Route::delete('/kennel/file/revert', 'revertFileUpload')->name('revert-file-kennel');
    });

    Route::controller(RoomController::class)->group(function () {
        Route::get('/rooms', 'listRooms')->name('rooms')->middleware('ensure.permission:28,can_read');
        Route::get('/room/add', 'addRoom')->name('add-room')->middleware('ensure.permission:28,can_create');
        Route::get('/room/edit/{id}', 'editRoom')->name('edit-room')->middleware('ensure.permission:28,can_update');
        Route::post('/room/create', 'createRoom')->name('create-room')->middleware('ensure.permission:28,can_create');
        Route::post('/room/update', 'updateRoom')->name('update-room')->middleware('ensure.permission:28,can_update');
        Route::post('/room/delete', 'deleteRoom')->name('delete-room')->middleware('ensure.permission:28,can_delete');
        Route::post('/room/file/process', 'processFileUpload')->name('process-file-room');
        Route::delete('/room/file/revert', 'revertFileUpload')->name('revert-file-room');
    });
});
