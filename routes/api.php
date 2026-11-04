<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\CameraController;
use App\Http\Controllers\Api\MaintenanceController;
use App\Http\Controllers\Api\RentalController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/staff/admin/overview', [AdminController::class, 'overview']);
    Route::get('/staff/admin/users', [AdminController::class, 'users']);
    Route::patch('/staff/admin/users/{user}/role', [AdminController::class, 'updateRole']);
    Route::get('/cameras', [CameraController::class, 'index']);
    Route::get('/rentals', [RentalController::class, 'index']);
    Route::post('/rentals', [RentalController::class, 'store']);
    Route::delete('/rentals/{rental}', [RentalController::class, 'cancel']);
    Route::get('/staff/rentals', [RentalController::class, 'staffIndex']);
    Route::patch('/staff/rentals/{rental}', [RentalController::class, 'staffUpdate']);
    Route::get('/maintenances', [MaintenanceController::class, 'index']);
    Route::post('/maintenances', [MaintenanceController::class, 'store']);
    Route::patch('/maintenances/{maintenance}', [MaintenanceController::class, 'update']);
});
