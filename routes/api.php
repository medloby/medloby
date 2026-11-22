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

use App\Http\Controllers\ClinicApplicationStatusController;

use App\Http\Controllers\ClinicBranchOperationsController;

use App\Http\Controllers\ClinicAppointmentController;

use App\Http\Controllers\ClinicCalendarController;

use App\Http\Controllers\ClinicCrmWorkspaceController;

use App\Http\Controllers\CrmLeadController;

use App\Http\Controllers\CrmLeadAppointmentController;

use App\Http\Controllers\CrmTaskController;

use App\Http\Controllers\CrmDashboardController;

use App\Http\Controllers\CrmFollowUpController;

use App\Http\Controllers\CrmConversationController;

use App\Http\Controllers\CrmActivityController;

use App\Http\Controllers\CrmPipelineStageController;

use App\Http\Controllers\TreatmentCategoryController;

use App\Http\Controllers\TreatmentController;

use App\Http\Controllers\TreatmentPackageController;

use App\Http\Controllers\TreatmentPackageItemController;

use App\Http\Controllers\TreatmentPriceController;

use App\Http\Controllers\BranchAppointmentSettingController;

use App\Http\Controllers\BookingCalendarController;

use App\Http\Controllers\BranchController;

use App\Http\Controllers\AuthController;

use App\Http\Controllers\BusinessNotificationPreferenceController;

use App\Http\Controllers\BusinessRegistrationController;

use App\Http\Controllers\ClinicProfileController;

use App\Http\Controllers\ConversationController;

use App\Http\Controllers\PackageServiceController;

use App\Http\Controllers\PackageServiceItemController;

use App\Http\Controllers\EmailVerificationController;

use App\Http\Controllers\MessageAttachmentController;

use App\Http\Controllers\SocialMediaAccountController;

use App\Http\Controllers\OfferController;

use App\Http\Controllers\PatientRegistrationController;

use App\Http\Controllers\NotificationController;

use App\Http\Middleware\EnsureBranchCatalogManagementAccess;

use App\Http\Middleware\EnsureClinicBranchOperationsAccess;

use App\Http\Middleware\EnsureClinicCrmAccess;

use App\Models\Appointment;

use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::post('/register/patient', [

    PatientRegistrationController::class,

    'store',

])->middleware('throttle:15,1,registration:');

Route::get('/register/business/contract', [BusinessRegistrationController::class, 'contract']);
Route::post('/register/business/application', [BusinessRegistrationController::class, 'requestApproval'])
    ->middleware('throttle:15,1,registration:');

Route::post('/register/business', [

    BusinessRegistrationController::class,

    'store',

])->middleware('throttle:15,1,registration:');

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

        Route::get('/clinic-applications', ClinicApplicationStatusController::class);
        Route::get('/clinic-crm/staff-workspaces', [ClinicCrmWorkspaceController::class, 'staffIndex']);
        Route::get('/businesses/{business}/crm', ClinicCrmWorkspaceController::class);
        Route::get('/businesses/{business}/crm/profile', [ClinicProfileController::class, 'show']);
        Route::put('/businesses/{business}/crm/profile', [ClinicProfileController::class, 'update']);

        /*

        |--------------------------------------------------------------------------

        | Admin - Business Applications & Status Management

        |--------------------------------------------------------------------------

        */

        Route::middleware('admin')

            ->prefix('admin/businesses')

            ->group(function () {

                Route::get('/directory', [AdminBusinessController::class, 'directory']);

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

                Route::delete('/{business}/members/{businessUser}/permissions/{permission}', [AdminBusinessMemberPermissionController::class, 'destroy']);

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

                Route::get('/{platformContract}/acceptances', [AdminPlatformContractController::class, 'acceptances']);

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

            Route::get('/{conversation}/offers/package-options', [OfferController::class, 'packageOptions']);

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

        Route::get('/offers/{offer}/appointments/options', [
            AppointmentBookingController::class,
            'options',
        ]);

        Route::get('/offers/{offer}/appointments', [
            AppointmentBookingController::class,
            'index',
        ]);

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

Route::controller(TreatmentCategoryController::class)->prefix('treatment-categories')->group(function () {
    Route::get('/', 'index');
    Route::get('/{treatmentCategory}', 'show');

    Route::middleware(['auth:sanctum', 'verified', 'admin'])->group(function () {
        Route::post('/', 'store');
        Route::put('/{treatmentCategory}', 'update');
        Route::delete('/{treatmentCategory}', 'deactivate');
    });
});

Route::controller(TreatmentController::class)->prefix('treatments')->group(function () {
    Route::get('/', 'index');
    Route::get('/{treatment}', 'show');

    Route::middleware(['auth:sanctum', 'verified', 'admin'])->group(function () {
        Route::post('/', 'store');
        Route::put('/{treatment}', 'update');
        Route::delete('/{treatment}', 'deactivate');
    });
});

Route::controller(BranchTreatmentController::class)->prefix('branches/{branch}/treatments')->group(function () {
    Route::get('/', 'index');

    Route::middleware(['auth:sanctum', 'verified', EnsureBranchCatalogManagementAccess::class])->group(function () {
        Route::post('/', 'store');
        Route::put('/{treatment}', 'update');
        Route::delete('/{treatment}', 'deactivate');
    });
});

Route::controller(TreatmentPriceController::class)->prefix('treatment-prices')->group(function () {
    Route::middleware(['auth:sanctum', 'verified'])->group(function () {
        Route::get('/', 'index');
        Route::get('/{treatmentPrice}', 'show');
        Route::post('/', 'store');
        Route::put('/{treatmentPrice}', 'update');
        Route::delete('/{treatmentPrice}', 'deactivate');
    });
});

Route::get('/package-catalog', [TreatmentPackageController::class, 'discover']);

Route::get('/treatment-packages/manage/{business}', [TreatmentPackageController::class, 'manage'])
    ->middleware(['auth:sanctum', 'verified']);

Route::controller(TreatmentPackageController::class)->prefix('treatment-packages')->group(function () {
    Route::get('/', 'index');
    Route::get('/{treatmentPackage}', 'show');

    Route::middleware(['auth:sanctum', 'verified'])->group(function () {
        Route::post('/', 'store');
        Route::put('/{treatmentPackage}', 'update');
        Route::delete('/{treatmentPackage}', 'deactivate');
    });
});

Route::controller(TreatmentPackageItemController::class)->prefix('treatment-packages/{treatmentPackage}/items')->group(function () {
    Route::get('/', 'index');
    Route::get('/{treatmentPackageItem}', 'show');

    Route::middleware(['auth:sanctum', 'verified'])->group(function () {
        Route::post('/', 'store');
        Route::put('/{treatmentPackageItem}', 'update');
        Route::delete('/{treatmentPackageItem}', 'destroy');
    });
});

Route::controller(PackageServiceController::class)->prefix('package-services')->group(function () {
    Route::get('/', 'index');
    Route::get('/{packageService}', 'show');

    Route::middleware(['auth:sanctum', 'verified'])->group(function () {
        Route::post('/', 'store');
        Route::put('/{packageService}', 'update');
        Route::delete('/{packageService}', 'deactivate');
    });
});

Route::controller(PackageServiceItemController::class)->prefix('treatment-packages/{treatmentPackage}/service-items')->group(function () {
    Route::get('/', 'index');
    Route::get('/{packageServiceItem}', 'show');

    Route::middleware(['auth:sanctum', 'verified'])->group(function () {
        Route::post('/', 'store');
        Route::put('/{packageServiceItem}', 'update');
        Route::delete('/{packageServiceItem}', 'destroy');
    });
});

Route::controller(SocialMediaAccountController::class)->prefix('branches/{branch}/social-media')->group(function () {
    Route::get('/', 'index');
    Route::get('/{socialMediaAccount}', 'show');

    Route::middleware(['auth:sanctum', 'verified', EnsureBranchCatalogManagementAccess::class])->group(function () {
        Route::post('/', 'store');
        Route::put('/{socialMediaAccount}', 'update');
        Route::delete('/{socialMediaAccount}', 'deactivate');
    });
});

Route::middleware(['auth:sanctum', 'verified', EnsureClinicCrmAccess::class, EnsureClinicBranchOperationsAccess::class])
    ->controller(ClinicBranchOperationsController::class)
    ->prefix('branches/{branch}/operations')->group(function () {
        Route::get('/', 'index');
        Route::post('/staff', 'addStaff');
        Route::post('/staff/accounts', 'createStaffAccount');
        Route::put('/staff/{businessUser}', 'updateStaff');
        Route::put('/staff/{businessUser}/password', 'resetStaffPassword');
        Route::post('/treatments', [BranchTreatmentController::class, 'store']);
        Route::put('/treatments/{treatment}', [BranchTreatmentController::class, 'update']);
        Route::post('/doctors', 'addDoctor');
        Route::put('/doctors/{doctor}', 'updateDoctorBranch');
        Route::put('/doctors/{doctor}/online-booking', 'updateDoctorOnlineBooking');
        Route::put('/doctors/{doctor}/treatments/{treatment}', 'updateDoctorTreatment');
    });

Route::middleware(['auth:sanctum', 'verified', EnsureClinicCrmAccess::class])
    ->prefix('branches/{branch}/clinic-calendar')->group(function () {
        Route::get('/', [ClinicCalendarController::class, 'show']);
        Route::get('/appointment-options', [ClinicAppointmentController::class, 'options']);
        Route::get('/patients', [ClinicAppointmentController::class, 'patients']);
        Route::get('/package-offers', [ClinicAppointmentController::class, 'packages']);
        Route::post('/appointments', [ClinicAppointmentController::class, 'store']);
        Route::get('/appointments/{appointment}/contact', [ClinicCalendarController::class, 'contact']);
        Route::post('/appointments/{appointment}/{action}', [AppointmentController::class, 'actFromCalendar'])
            ->whereIn('action', array_keys(Appointment::ACTION_PERMISSIONS));
        Route::get('/package-offers/{offer}/appointments/options', [AppointmentBookingController::class, 'optionsForClinic']);
        Route::post('/package-offers/{offer}/appointments', [AppointmentBookingController::class, 'storeForClinic']);

        Route::middleware(EnsureClinicBranchOperationsAccess::class)->group(function () {
            Route::get('/rules', [BookingCalendarController::class, 'show']);
            Route::put('/rules', [BookingCalendarController::class, 'updateRule']);
            Route::post('/overrides', [BookingCalendarController::class, 'createOverride']);
            Route::put('/overrides/{override}', [BookingCalendarController::class, 'updateOverride']);
            Route::delete('/overrides/{override}', [BookingCalendarController::class, 'deactivateOverride']);
            Route::post('/blocks', [CalendarBlockController::class, 'store']);
            Route::put('/blocks/{calendarBlock}', [CalendarBlockController::class, 'update']);
            Route::delete('/blocks/{calendarBlock}', [CalendarBlockController::class, 'deactivate']);
            Route::get('/doctors/{doctor}/working-hours', [DoctorWorkingHourController::class, 'index']);
            Route::post('/doctors/{doctor}/working-hours', [DoctorWorkingHourController::class, 'store']);
            Route::put('/working-hours/{doctorWorkingHour}', [DoctorWorkingHourController::class, 'updateForBranch']);
            Route::get('/doctors/{doctor}/leaves', [DoctorLeaveController::class, 'index']);
            Route::post('/doctors/{doctor}/leaves', [DoctorLeaveController::class, 'store']);
            Route::put('/doctors/{doctor}/leaves/{doctorLeave}', [DoctorLeaveController::class, 'update']);
            Route::delete('/doctors/{doctor}/leaves/{doctorLeave}', [DoctorLeaveController::class, 'deactivate']);
        });
    });

Route::middleware(['auth:sanctum', 'verified', EnsureClinicCrmAccess::class])
    ->prefix('branches/{branch}/crm')
    ->group(function () {
        Route::prefix('leads')->group(function () {
            Route::get('/', [
                CrmLeadController::class,
                'index',
            ]);

            Route::post('/', [
                CrmLeadController::class,
                'store',
            ]);

            Route::get('/assignees', [CrmLeadController::class, 'assignees']);

            Route::get('/{crmLead}', [
                CrmLeadController::class,
                'show',
            ]);

            Route::get('/{crmLead}/appointments', CrmLeadAppointmentController::class);

            Route::put('/{crmLead}', [
                CrmLeadController::class,
                'update',
            ]);

            Route::post('/{crmLead}/convert', [
                CrmLeadController::class,
                'convert',
            ]);

            Route::post('/{crmLead}/lost', [
                CrmLeadController::class,
                'markLost',
            ]);
        });

        Route::prefix('pipeline-stages')->group(function () {
            Route::get('/', [
                CrmPipelineStageController::class,
                'index',
            ]);

            Route::post('/', [
                CrmPipelineStageController::class,
                'store',
            ]);

            Route::get('/{crmPipelineStage}', [
                CrmPipelineStageController::class,
                'show',
            ]);

            Route::put('/{crmPipelineStage}', [
                CrmPipelineStageController::class,
                'update',
            ]);

            Route::delete('/{crmPipelineStage}', [
                CrmPipelineStageController::class,
                'deactivate',
            ]);
        });

        Route::prefix('tasks')->group(function () {
            Route::get('/', [
                CrmTaskController::class,
                'index',
            ]);

            Route::post('/', [
                CrmTaskController::class,
                'store',
            ]);

            Route::get('/{crmTask}', [
                CrmTaskController::class,
                'show',
            ]);

            Route::put('/{crmTask}', [
                CrmTaskController::class,
                'update',
            ]);

            Route::post('/{crmTask}/complete', [
                CrmTaskController::class,
                'complete',
            ]);

            Route::post('/{crmTask}/cancel', [
                CrmTaskController::class,
                'cancel',
            ]);
        });

        Route::prefix('activities')->group(function () {
            Route::get('/', [
                CrmActivityController::class,
                'index',
            ]);

            Route::post('/', [
                CrmActivityController::class,
                'store',
            ]);

            Route::get('/{crmActivity}', [
                CrmActivityController::class,
                'show',
            ]);

            Route::put('/{crmActivity}', [
                CrmActivityController::class,
                'update',
            ]);

            Route::delete('/{crmActivity}', [
                CrmActivityController::class,
                'destroy',
            ]);
        });

        Route::prefix('follow-ups')->group(function () {
            Route::get('/', [
                CrmFollowUpController::class,
                'index',
            ]);

            Route::post('/', [
                CrmFollowUpController::class,
                'store',
            ]);

            Route::get('/{crmFollowUp}', [
                CrmFollowUpController::class,
                'show',
            ]);

            Route::put('/{crmFollowUp}', [
                CrmFollowUpController::class,
                'update',
            ]);

            Route::post('/{crmFollowUp}/complete', [
                CrmFollowUpController::class,
                'complete',
            ]);

            Route::post('/{crmFollowUp}/cancel', [
                CrmFollowUpController::class,
                'cancel',
            ]);
        });

        Route::prefix('leads/{crmLead}/conversations')->group(function () {
            Route::get('/', [CrmConversationController::class, 'index']);

            Route::post('/{conversation}/attach', [
                CrmConversationController::class,
                'attachLead',
            ]);
        });

        Route::get(
            'dashboard',
            [
                CrmDashboardController::class,
                'index',
            ]
        );
    });
