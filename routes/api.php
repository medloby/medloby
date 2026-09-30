<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusinessRegistrationController;
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

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::prefix('appointments')->group(function () {
        Route::post('/{appointment}/confirm', [AppointmentController::class, 'confirm']);
        Route::post('/{appointment}/cancel', [AppointmentController::class, 'cancel']);
        Route::post('/{appointment}/complete', [AppointmentController::class, 'complete']);
        Route::post('/{appointment}/no-show', [AppointmentController::class, 'noShow']);
        Route::post('/{appointment}/reschedule', [AppointmentController::class, 'reschedule']);
    });
});