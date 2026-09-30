<?php

use App\Http\Controllers\AppointmentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->prefix('appointments')
    ->group(function () {
        Route::post('/{appointment}/confirm', [AppointmentController::class, 'confirm']);
        Route::post('/{appointment}/cancel', [AppointmentController::class, 'cancel']);
        Route::post('/{appointment}/complete', [AppointmentController::class, 'complete']);
        Route::post('/{appointment}/no-show', [AppointmentController::class, 'noShow']);
        Route::post('/{appointment}/reschedule', [AppointmentController::class, 'reschedule']);
    });