<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::view('/paket-teklifleri', 'package-offers')->name('package-offers');
