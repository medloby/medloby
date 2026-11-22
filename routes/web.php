<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::view('/paket-teklifleri', 'package-offers')->name('package-offers');
