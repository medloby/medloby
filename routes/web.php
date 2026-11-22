<?php

use App\Http\Controllers\PrivacyNoticeController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::view('/paket-teklifleri', 'package-offers')->name('package-offers');
Route::get('/kvkk/{audience}', PrivacyNoticeController::class)
    ->whereIn('audience', ['hasta', 'klinik'])->name('privacy.notice');
