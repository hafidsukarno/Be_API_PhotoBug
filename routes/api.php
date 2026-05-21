<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PublicController;

use App\Http\Controllers\Admin\PenyuluhController;
use App\Http\Controllers\Admin\VillageController as AdminVillageController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Petani\DetectionController;
use App\Http\Controllers\Petani\HistoryController;
use App\Http\Controllers\Petani\VillageController as PetaniVillageController;
use App\Http\Controllers\Penyuluh\DetectionController as PenyuluhDetectionController;
use App\Http\Controllers\Penyuluh\RecommendationController;
use App\Http\Controllers\Penyuluh\VillageController as PenyuluhVillageController;
use App\Http\Controllers\TelegramWebhookController;

// Handle CORS preflight requests
Route::options('{any}', function () {
    return response()->json(null, 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
})->where('any', '.*');

// Image serving endpoint dengan CORS
Route::get('/image/{path}', function ($path) {
    if (Storage::disk('public')->exists($path)) {
        $file = Storage::disk('public')->get($path);
        return response($file, 200)
            ->header('Content-Type', 'image/jpeg')
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Cache-Control', 'public, max-age=3600');
    }
    
    return response()->json(['error' => 'File not found'], 404)
        ->header('Access-Control-Allow-Origin', '*');
})->where('path', '.*');

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Public endpoint untuk mendapatkan daftar village untuk registrasi
Route::get('/villages', [PublicController::class, 'getVillagesForRegistration']);

// Endpoint untuk di-hit oleh Server Telegram (Ngrok) -- TANPA AUTH
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user()->load('role', 'village', 'managedVillages');
    });

    // Rutin khusus Petani
    Route::prefix('petani')->group(function () {
        Route::get('/village', [PetaniVillageController::class, 'show']);
        Route::post('/detections', [DetectionController::class, 'store']);
        Route::get('/detections/{id}', [DetectionController::class, 'show']);
        Route::get('/history', [HistoryController::class, 'index']);
        Route::get('/report-status', [DetectionController::class, 'reportStatus']);
    });

    // Rutin khusus Penyuluh
    Route::prefix('penyuluh')->group(function () {
        Route::get('/villages', [PenyuluhVillageController::class, 'index']);
        Route::get('/detections', [PenyuluhDetectionController::class, 'index']);
        Route::get('/detections/{id}', [PenyuluhDetectionController::class, 'show']);
        Route::post('/detections/{id}/recommendations', [RecommendationController::class, 'store']);
        Route::get('/report-status', [PenyuluhDetectionController::class, 'reportStatus']);
        Route::get('/pest-trend', [PenyuluhDetectionController::class, 'pestTrend']);
        Route::get('/notifications', [PenyuluhDetectionController::class, 'getNotifications']);
    });

    // Rutin khusus Admin
    Route::middleware('admin')->prefix('admin')->group(function () {
        // Analytics Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/pest-statistics', [DashboardController::class, 'pestStatistics']);
        Route::get('/reports', [DashboardController::class, 'getAllReports']);
        Route::get('/reports/{id}', [DashboardController::class, 'getReportDetail']);

        // Kelola Desa
        Route::get('/villages', [AdminVillageController::class, 'index']);
        Route::get('/villages-report', [AdminVillageController::class, 'getVillagesReport']);
        Route::post('/villages', [AdminVillageController::class, 'store']);
        Route::put('/villages/{id}', [AdminVillageController::class, 'update']);
        Route::delete('/villages/{id}', [AdminVillageController::class, 'destroy']);

        // Kelola Penyuluh
        Route::get('/penyuluh', [PenyuluhController::class, 'index']);
        Route::post('/penyuluh', [PenyuluhController::class, 'store']);
        Route::put('/penyuluh/{id}', [PenyuluhController::class, 'update']);
        Route::delete('/penyuluh/{id}', [PenyuluhController::class, 'destroy']);
    });
});
