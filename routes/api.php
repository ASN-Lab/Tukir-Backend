<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\IngestController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);

// Webhook EMQX Rule Engine (autentikasi via header X-Ingest-Secret)
Route::post('/internal/ingest', IngestController::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/devices', [DeviceController::class, 'index']);
    Route::post('/devices/{id}/control', [DeviceController::class, 'control']);

    Route::middleware('role:SUPER_ADMIN,TECHNICIAN')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    });
});
