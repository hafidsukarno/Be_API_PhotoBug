<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Detection;
use App\Models\DetectionResult;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // 1. Total Laporan (Semua, Menunggu, Selesai)
        $totalDetections = Detection::count();
        $pendingDetections = Detection::where('status', 'pending')->count();
        $completedDetections = Detection::where('status', 'completed')->count();

        // 2. Pengguna Aktif
        // Active Petani (yang pernah melaporkan)
        $activePetani = User::whereHas('detections')->where('role_id', 3)->count();
        $totalPetani = User::where('role_id', 3)->count();
        
        // Active Penyuluh (yang pernah memberikan rekomendasi atau handling deteksi)
        $activePenyuluh = User::whereHas('managedVillages.users.detections')->where('role_id', 2)->count();
        $totalPenyuluh = User::where('role_id', 2)->count();
        
        // Active Admin (role_id 1)
        $activeAdmin = User::where('role_id', 1)->count();
        $totalAdmin = User::where('role_id', 1)->count();

        // Total users
        $totalUsers = $totalPetani + $totalPenyuluh + $totalAdmin;
        $totalActiveUsers = $activePetani + $activePenyuluh + $activeAdmin;

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_users' => $totalUsers,
                'active_users' => $totalActiveUsers,
                'total_reports' => $totalDetections,
                'pending_review' => $pendingDetections,
                'completed' => $completedDetections
            ]
        ]);
    }

    // Get distribusi hama sederhana (Wereng Coklat & Hijau)
    public function pestStatistics()
    {
        $werengCoklat = DB::table('detection_results')
            ->where('pest_name', 'wereng coklat')
            ->count();

        $werengHijau = DB::table('detection_results')
            ->where('pest_name', 'wereng hijau')
            ->count();

        return response()->json([
            'status' => 'success',
            'summary' => [
                'wereng_coklat' => $werengCoklat,
                'wereng_hijau' => $werengHijau
            ]
        ]);
    }

    // Get semua laporan (master report) dengan filter dan pagination
    public function getAllReports(Request $request)
    {
        $status = $request->query('status'); // 'pending', 'completed', atau null (semua)

        $query = Detection::with('user', 'detectionResults', 'recommendations.createdBy')
                         ->orderBy('detected_at', 'desc');

        // Filter berdasarkan status
        if ($status) {
            $query->where('status', $status);
        }

        $detections = $query->get();

        return response()->json([
            'total' => count($detections),
            'pending_count' => Detection::where('status', 'pending')->count(),
            'completed_count' => Detection::where('status', 'completed')->count(),
            'data' => $detections
        ]);
    }

    // Get detail laporan spesifik untuk admin
    public function getReportDetail($id)
    {
        $detection = Detection::with('user', 'detectionResults', 'recommendations.createdBy')->findOrFail($id);

        return response()->json($detection);
    }
}
