<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ActivityLogController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);

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
Route::post('/internal/tunnel-update', function (Illuminate\Http\Request $request) {
    if ($request->secret !== 'tukir-secret-update-99') return response()->json(['error' => 'Unauthorized'], 401);
    
    $host = $request->host;
    // Update .env file (ini mungkin tidak permanen di Wasmer, tapi kita coba panggil config)
    // Cara terbaik di Wasmer adalah simpan di DB atau Cache
    Cache::put('mqtt_tunnel_host', $host);
    return response()->json(['success' => true, 'new_host' => $host]);
});
