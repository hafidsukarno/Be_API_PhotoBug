<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Detection;
use App\Models\DetectionResult;
use App\Models\User;
use App\Models\Village;
use App\Exports\StatisticsReportExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

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

    // Download Laporan Statistik Excel (dengan filter desa & bulan)
    public function downloadStatisticsReport(Request $request)
    {
        try {
            // Validasi input
            $validated = $request->validate([
                'village_ids' => 'nullable|array',
                'village_ids.*' => 'integer|exists:villages,id',
                'date_from' => 'required|date',
                'date_to' => 'required|date|after_or_equal:date_from'
            ]);

            // Base query untuk detections
            $query = Detection::with(['user.village', 'user.managedVillages', 'detectionResults'])
                ->whereBetween(DB::raw('DATE(detected_at)'), [
                    $validated['date_from'],
                    $validated['date_to']
                ]);

            // Filter berdasarkan village jika ada
            if (!empty($validated['village_ids'])) {
                $query->whereHas('user', function ($q) use ($validated) {
                    $q->whereIn('village_id', $validated['village_ids']);
                });
            }

            $detections = $query->get();

            // Group data by village
            $villageData = [];
            $villageMap = [];

            foreach ($detections as $detection) {
                $village = $detection->user->village;
                if (!$village) continue;

                $villageId = $village->id;

                if (!isset($villageData[$villageId])) {
                    // Get penyuluh untuk desa ini
                    $penyuluh = $village->penyuluh ? $village->penyuluh->name : 'Belum ditentukan';

                    $villageData[$villageId] = [
                        'village_name' => $village->village_name,
                        'district' => $village->district,
                        'penyuluh' => $penyuluh,
                        'total_reports' => 0,
                        'wereng_coklat' => 0,
                        'wereng_hijau' => 0,
                        'tanggal_laporan' => []
                    ];
                    $villageMap[$villageId] = count($villageData);
                }

                $villageData[$villageId]['total_reports']++;
                $villageData[$villageId]['tanggal_laporan'][] = $detection->detected_at->format('d-m-Y H:i');

                // Count hama per deteksi
                foreach ($detection->detectionResults as $result) {
                    if (strtolower($result->pest_name) === 'wereng coklat') {
                        $villageData[$villageId]['wereng_coklat']++;
                    } elseif (strtolower($result->pest_name) === 'wereng hijau') {
                        $villageData[$villageId]['wereng_hijau']++;
                    }
                }
            }

            // Format data untuk export
            $exportData = [];
            $no = 1;

            foreach ($villageData as $data) {
                $exportData[] = [
                    $no++,
                    $data['village_name'],
                    $data['district'],
                    $data['total_reports'],
                    $data['wereng_coklat'],
                    $data['wereng_hijau'],
                    implode(', ', $data['tanggal_laporan']),
                    $data['penyuluh']
                ];
            }

            if (empty($exportData)) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Tidak ada data laporan untuk periode yang dipilih'
                ]);
            }

            // Generate filename
            $dateFrom = $request->date_from;
            $dateTo = $request->date_to;
            $filename = "Laporan_Statistik_{$dateFrom}_to_{$dateTo}.xlsx";

            // Export ke Excel
            return Excel::download(
                new StatisticsReportExport(
                    $exportData,
                    "Periode: {$dateFrom} s/d {$dateTo}"
                ),
                $filename
            );

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal generate laporan: ' . $e->getMessage()
            ], 500);
        }
    }

}
