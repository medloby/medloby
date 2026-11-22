<?php

use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PrivacyNoticeController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::view('/paket-teklifleri', 'package-offers')->name('package-offers');
Route::get('/kvkk/{audience}', PrivacyNoticeController::class)
    ->whereIn('audience', ['hasta', 'klinik'])->name('privacy.notice');

Route::controller(PasswordResetController::class)->group(function () {
    Route::get('/sifremi-unuttum', 'requestForm')->name('password.request');
    Route::post('/sifremi-unuttum', 'sendLink')->middleware('throttle:5,1,password-email:')->name('password.email');
    Route::get('/sifre-yenile', 'resetForm')->name('password.reset');
    Route::post('/sifre-yenile', 'reset')->middleware('throttle:10,1,password-reset:')->name('password.update');
});
