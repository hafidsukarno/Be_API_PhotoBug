<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

use App\Http\Controllers\Admin\PenyuluhController;
use App\Http\Controllers\Admin\VillageController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Petani\DetectionController;
use App\Http\Controllers\Petani\HistoryController;
use App\Http\Controllers\Penyuluh\DetectionController as PenyuluhDetectionController;
use App\Http\Controllers\Penyuluh\RecommendationController;
use App\Http\Controllers\TelegramWebhookController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Endpoint untuk di-hit oleh Server Telegram (Ngrok) -- TANPA AUTH
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user()->load('role', 'village', 'managedVillages');
    });

    // Rutin khusus Petani
    Route::prefix('petani')->group(function () {
        Route::post('/detections', [DetectionController::class, 'store']);
        Route::get('/detections/{id}', [DetectionController::class, 'show']);
        Route::get('/history', [HistoryController::class, 'index']);
    });

    // Rutin khusus Penyuluh
    Route::prefix('penyuluh')->group(function () {
        Route::get('/detections', [PenyuluhDetectionController::class, 'index']);
        Route::get('/detections/{id}', [PenyuluhDetectionController::class, 'show']);
        Route::post('/detections/{id}/recommendations', [RecommendationController::class, 'store']);
    });

    // Rutin khusus Admin
    Route::middleware('admin')->prefix('admin')->group(function () {
        // Analytics Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);

        // Kelola Desa
        Route::get('/villages', [VillageController::class, 'index']);
        Route::post('/villages', [VillageController::class, 'store']);
        Route::put('/villages/{id}', [VillageController::class, 'update']);
        Route::delete('/villages/{id}', [VillageController::class, 'destroy']);

        // Kelola Penyuluh
        Route::get('/penyuluh', [PenyuluhController::class, 'index']);
        Route::post('/penyuluh', [PenyuluhController::class, 'store']);
        Route::put('/penyuluh/{id}', [PenyuluhController::class, 'update']);
        Route::delete('/penyuluh/{id}', [PenyuluhController::class, 'destroy']);
    });
});
