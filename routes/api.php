<?php

use App\Http\Controllers\AdminBusinessController;
use App\Http\Controllers\AdminBusinessMemberPermissionController;
use App\Http\Controllers\AdminBusinessMemberRoleController;
use App\Http\Controllers\AdminBusinessMemberStatusController;
use App\Http\Controllers\AdminBusinessMembershipController;
use App\Http\Controllers\AdminBusinessMembershipDetailController;
use App\Http\Controllers\AdminBusinessPermissionController;
use App\Http\Controllers\AdminBusinessStatusController;
use App\Http\Controllers\AdminBusinessStatusHistoryController;
use App\Http\Controllers\AdminPlatformContractController;
use App\Http\Controllers\AppointmentBookingController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusinessRegistrationController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\MessageAttachmentController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\PatientRegistrationController;
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

        Route::prefix('appointments')->group(function () {
            Route::get('/', [
                AppointmentController::class,
                'index',
            ]);

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