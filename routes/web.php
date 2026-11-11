<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/run-migration-secret-123', function () {
    \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    return \Illuminate\Support\Facades\Artisan::output();
});
