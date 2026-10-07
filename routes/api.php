<?php

use App\Http\Controllers\AdminBusinessController;

use App\Http\Controllers\AdminBusinessMemberPermissionController;

use App\Http\Controllers\AdminBusinessMemberRoleController;

use App\Http\Controllers\AdminBusinessMemberStatusController;

use App\Http\Controllers\AdminBusinessMembershipController;

use App\Http\Controllers\BranchTreatmentController;

use App\Http\Controllers\AdminBusinessMembershipDetailController;

use App\Http\Controllers\AdminBusinessPermissionController;

use App\Http\Controllers\AdminBusinessStatusController;

use App\Http\Controllers\AdminBusinessStatusHistoryController;

use App\Http\Controllers\AdminPlatformContractController;

use App\Http\Controllers\AppointmentBookingController;

use App\Http\Controllers\AppointmentController;

use App\Http\Controllers\DoctorWorkingHourController;

use App\Http\Controllers\DoctorLeaveController;

use App\Http\Controllers\CalendarBlockController;

use App\Http\Controllers\TreatmentCategoryController;

use App\Http\Controllers\TreatmentController;

use App\Http\Controllers\TreatmentPackageController;

use App\Http\Controllers\TreatmentPriceController;

use App\Http\Controllers\BranchAppointmentSettingController;

use App\Http\Controllers\BookingCalendarController;

use App\Http\Controllers\BranchController;

use App\Http\Controllers\AuthController;

use App\Http\Controllers\BusinessNotificationPreferenceController;

use App\Http\Controllers\BusinessRegistrationController;

use App\Http\Controllers\ConversationController;

use App\Http\Controllers\EmailVerificationController;

use App\Http\Controllers\MessageAttachmentController;

use App\Http\Controllers\OfferController;

use App\Http\Controllers\PatientRegistrationController;

use App\Http\Controllers\NotificationController;

use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::post('/register/patient', [

    PatientRegistrationController::class,

    'store',

]);

Route::post('/register/business', [

    BusinessRegistrationController::class,

    'store',

]);

Route::get('/email/verify/{id}/{hash}', [

    EmailVerificationController::class,

    'verify',

])

    ->middleware(['signed', 'throttle:6,1'])

    ->name('verification.verify');

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/me', [AuthController::class, 'me']);

    Route::post('/logout', [

        AuthController::class,

        'logout',

    ]);

    Route::post('/email/verification-notification', [

        EmailVerificationController::class,

        'resend',

    ])->middleware('throttle:6,1');

    Route::middleware('verified')->group(function () {

        /*

        |--------------------------------------------------------------------------

        | Admin - Business Applications & Status Management

        |--------------------------------------------------------------------------

        */

        Route::middleware('admin')

            ->prefix('admin/businesses')

            ->group(function () {

                Route::get('/', [

                    AdminBusinessController::class,

                    'index',

                ]);

                Route::get('/{business}', [

                    AdminBusinessController::class,

                    'show',

                ]);

                Route::post('/{business}/approve', [

                    AdminBusinessController::class,

                    'approve',

                ]);

                Route::post('/{business}/reject', [

                    AdminBusinessController::class,

                    'reject',

                ]);

                Route::post('/{business}/suspend', [

                    AdminBusinessStatusController::class,

                    'suspend',

                ]);

                Route::post('/{business}/reactivate', [

                    AdminBusinessStatusController::class,

                    'reactivate',

                ]);

                Route::get('/{business}/status-history', [

                    AdminBusinessStatusHistoryController::class,

                    'index',

                ]);

                Route::get('/{business}/members', [

                    AdminBusinessMembershipController::class,

                    'index',

                ]);

                Route::get('/{business}/members/{businessUser}', [

                    AdminBusinessMembershipDetailController::class,

                    'show',

                ]);

                Route::put(

                    '/{business}/members/{businessUser}/role',

                    [

                        AdminBusinessMemberRoleController::class,

                        'update',

                    ]

                );

                Route::put(

                    '/{business}/members/{businessUser}/status',

                    [

                        AdminBusinessMemberStatusController::class,

                        'update',

                    ]

                );

                Route::put(

                    '/{business}/members/{businessUser}/permissions/{permission}',

                    [

                        AdminBusinessMemberPermissionController::class,

                        'update',

                    ]

                );

                Route::get('/{business}/permissions', [

                    AdminBusinessPermissionController::class,

                    'index',

                ]);

            });

        /*

        |--------------------------------------------------------------------------

        | Admin - Platform Contracts

        |--------------------------------------------------------------------------

        */

        Route::middleware('admin')

            ->prefix('admin/platform-contracts')

            ->group(function () {

                Route::get('/', [

                    AdminPlatformContractController::class,

                    'index',

                ]);

                Route::post('/', [

                    AdminPlatformContractController::class,

                    'store',

                ]);

                Route::get('/{platformContract}', [

                    AdminPlatformContractController::class,

                    'show',

                ]);

                Route::put('/{platformContract}', [

                    AdminPlatformContractController::class,

                    'update',

                ]);

                Route::post('/{platformContract}/publish', [

                    AdminPlatformContractController::class,

                    'publish',

                ]);

                Route::post('/{platformContract}/deactivate', [

                    AdminPlatformContractController::class,

                    'deactivate',

                ]);

            });

        /*

        |--------------------------------------------------------------------------

        | Business Notification Preferences

        |--------------------------------------------------------------------------

        */

        Route::prefix(

            'businesses/{business}/notification-preferences'

        )->group(function () {

            Route::get('/', [

                BusinessNotificationPreferenceController::class,

                'index',

            ]);

            Route::put('/{type}', [

                BusinessNotificationPreferenceController::class,

                'update',

            ]);

            Route::post('/reset', [

                BusinessNotificationPreferenceController::class,

                'reset',

            ]);

        });

/*

\\|--------------------------------------------------------------------------

\\| Notifications

\\|--------------------------------------------------------------------------

*/

Route::prefix('notifications')->group(function () {

    Route::get('/', [

        NotificationController::class,

        'index',

    ]);

    Route::get('/unread', [

        NotificationController::class,

        'unread',

    ]);

    Route::get('/unread-count', [

        NotificationController::class,

        'unreadCount',

    ]);

    Route::post('/read-all', [

        NotificationController::class,

        'markAllAsRead',

    ]);

    Route::post('/{notification}/read', [

        NotificationController::class,

        'markAsRead',

    ]);

    Route::delete('/{notification}', [

        NotificationController::class,

        'destroy',

    ]);

});

        /*

        |--------------------------------------------------------------------------

        | Conversations

        |--------------------------------------------------------------------------

        */

        Route::prefix('conversations')->group(function () {

            Route::get('/', [

                ConversationController::class,

                'index',

            ]);

            Route::post('/', [

                ConversationController::class,

                'store',

            ]);

            Route::post('/{conversation}/messages', [

                ConversationController::class,

                'sendMessage',

            ]);

            Route::get('/{conversation}', [

                ConversationController::class,

                'show',

            ]);

            Route::get('/{conversation}/offers', [

                OfferController::class,

                'index',

            ]);

            Route::post('/{conversation}/offers', [

                OfferController::class,

                'store',

            ]);

        });

        /*

        |--------------------------------------------------------------------------

        | Offers

        |--------------------------------------------------------------------------

        */

        Route::post('/offers/{offer}/accept', [

            OfferController::class,

            'accept',

        ]);

        Route::post('/offers/{offer}/reject', [

            OfferController::class,

            'reject',

        ]);

        /*

        |--------------------------------------------------------------------------

        | Appointment Booking

        |--------------------------------------------------------------------------

        */

        Route::post('/offers/{offer}/appointments', [

            AppointmentBookingController::class,

            'store',

        ]);

        /*

        |--------------------------------------------------------------------------

        | Message Attachments

        |--------------------------------------------------------------------------

        */

        Route::get('/attachments/{attachment}/view', [

            MessageAttachmentController::class,

            'view',

        ]);

        /*

        |--------------------------------------------------------------------------

        | Appointments

        |--------------------------------------------------------------------------

        */

                /*

        |--------------------------------------------------------------------------

        | Doctor Working Hours

        |--------------------------------------------------------------------------

        */

        Route::prefix('branches/{branch}/doctors/{doctor}/working-hours')

            ->group(function () {

                Route::get('/', [

                    DoctorWorkingHourController::class,

                    'index',

                ]);

                Route::post('/', [

                    DoctorWorkingHourController::class,

                    'store',

                ]);

            });

        Route::prefix('doctor-working-hours/{doctorWorkingHour}')

            ->group(function () {

                Route::put('/', [

                    DoctorWorkingHourController::class,

                    'update',

                ]);

                Route::patch('/toggle', [

                    DoctorWorkingHourController::class,

                    'toggle',

                ]);

                Route::delete('/', [

                    DoctorWorkingHourController::class,

                    'destroy',

                ]);

            });

            Route::get('/branches/{branch}/doctors/{doctor}/leaves', [
    DoctorLeaveController::class,
    'index',
]);

Route::post('/branches/{branch}/doctors/{doctor}/leaves', [
    DoctorLeaveController::class,
    'store',
]);

Route::put('/branches/{branch}/doctors/{doctor}/leaves/{doctorLeave}', [
    DoctorLeaveController::class,
    'update',
]);

Route::delete('/branches/{branch}/doctors/{doctor}/leaves/{doctorLeave}', [
    DoctorLeaveController::class,
    'deactivate',
]);

Route::get('/branches/{branch}/calendar-blocks', [
    CalendarBlockController::class,
    'index',
]);

Route::post('/branches/{branch}/calendar-blocks', [
    CalendarBlockController::class,
    'store',
]);

Route::put('/branches/{branch}/calendar-blocks/{calendarBlock}', [
    CalendarBlockController::class,
    'update',
]);

Route::delete('/branches/{branch}/calendar-blocks/{calendarBlock}', [
    CalendarBlockController::class,
    'deactivate',
]);

        /*
        |--------------------------------------------------------------------------
        | Branch Appointment Settings
        |--------------------------------------------------------------------------
        */

        Route::prefix('branches/{branch}/appointment-settings')->group(function () {
            Route::get('/', [
                BranchAppointmentSettingController::class,
                'show',
            ]);

            Route::post('/', [
                BranchAppointmentSettingController::class,
                'store',
            ]);

            Route::put('/', [
                BranchAppointmentSettingController::class,
                'update',
            ]);

            Route::post('/reset', [
                BranchAppointmentSettingController::class,
                'reset',
            ]);
        });

/*
|--------------------------------------------------------------------------
| Branch Management
|--------------------------------------------------------------------------
*/

Route::prefix('branches')->group(function () {
    Route::get('/', [
        BranchController::class,
        'index',
    ]);

    Route::post('/', [
        BranchController::class,
        'store',
    ]);

    Route::get('/{branch}', [
        BranchController::class,
        'show',
    ]);

    Route::put('/{branch}', [
        BranchController::class,
        'update',
    ]);

    Route::delete('/{branch}', [
        BranchController::class,
        'deactivate',
    ]);
});
   
Route::prefix('branches/{branch}/booking-calendar')->group(function () {
    Route::get('/', [
        BookingCalendarController::class,
        'show',
    ]);

    Route::put('/', [
        BookingCalendarController::class,
        'updateRule',
    ]);

    Route::get('/overrides', [
        BookingCalendarController::class,
        'overrides',
    ]);

    Route::post('/overrides', [
        BookingCalendarController::class,
        'createOverride',
    ]);

    Route::put('/overrides/{override}', [
        BookingCalendarController::class,
        'updateOverride',
    ]);

    Route::delete('/overrides/{override}', [
        BookingCalendarController::class,
        'deactivateOverride',
    ]);
});

Route::prefix('appointments')->group(function () {

            Route::get('/', [

                AppointmentController::class,

                'index',

            ]);

Route::get('/availability', [AppointmentController::class, 'availability']);

            Route::get(

    '/options',

    [AppointmentController::class, 'options']

);

            Route::post('/', [

                AppointmentController::class,

                'store',

            ]);

            Route::get('/{appointment}', [

                AppointmentController::class,

                'show',

            ]);

            Route::post('/{appointment}/confirm', [

                AppointmentController::class,

                'confirm',

            ]);

            Route::post('/{appointment}/cancel', [

                AppointmentController::class,

                'cancel',

            ]);

            Route::post('/{appointment}/complete', [

                AppointmentController::class,

                'complete',

            ]);

            Route::post('/{appointment}/no-show', [

                AppointmentController::class,

                'noShow',

            ]);

            Route::post('/{appointment}/reschedule', [

                AppointmentController::class,

                'reschedule',

            ]);

        });

    });

});

Route::prefix('treatment-categories')->group(function () {
    Route::get('/', [
        TreatmentCategoryController::class,
        'index',
    ]);

    Route::post('/', [
        TreatmentCategoryController::class,
        'store',
    ]);

    Route::get('/{treatmentCategory}', [
        TreatmentCategoryController::class,
        'show',
    ]);

    Route::put('/{treatmentCategory}', [
        TreatmentCategoryController::class,
        'update',
    ]);

    Route::delete('/{treatmentCategory}', [
        TreatmentCategoryController::class,
        'deactivate',
    ]);
});

Route::prefix('treatments')->group(function () {
    Route::get('/', [
        TreatmentController::class,
        'index',
    ]);

    Route::post('/', [
        TreatmentController::class,
        'store',
    ]);

    Route::get('/{treatment}', [
        TreatmentController::class,
        'show',
    ]);

    Route::put('/{treatment}', [
        TreatmentController::class,
        'update',
    ]);

    Route::delete('/{treatment}', [
        TreatmentController::class,
        'deactivate',
    ]);
});

Route::prefix('branches/{branch}/treatments')->group(function () {
    Route::get('/', [
        BranchTreatmentController::class,
        'index',
    ]);

    Route::post('/', [
        BranchTreatmentController::class,
        'store',
    ]);

    Route::put('/{treatment}', [
        BranchTreatmentController::class,
        'update',
    ]);

    Route::delete('/{treatment}', [
        BranchTreatmentController::class,
        'deactivate',
    ]);
});

Route::prefix('treatment-prices')->group(function () {
    Route::get('/', [
        TreatmentPriceController::class,
        'index',
    ]);

    Route::post('/', [
        TreatmentPriceController::class,
        'store',
    ]);

    Route::get('/{treatmentPrice}', [
        TreatmentPriceController::class,
        'show',
    ]);

    Route::put('/{treatmentPrice}', [
        TreatmentPriceController::class,
        'update',
    ]);

    Route::delete('/{treatmentPrice}', [
        TreatmentPriceController::class,
        'deactivate',
    ]);
});

Route::prefix('treatment-packages')->group(function () {
    Route::get('/', [
        TreatmentPackageController::class,
        'index',
    ]);

    Route::post('/', [
        TreatmentPackageController::class,
        'store',
    ]);

    Route::get('/{treatmentPackage}', [
        TreatmentPackageController::class,
        'show',
    ]);

    Route::put('/{treatmentPackage}', [
        TreatmentPackageController::class,
        'update',
    ]);

    Route::delete('/{treatmentPackage}', [
        TreatmentPackageController::class,
        'deactivate',
    ]);
});