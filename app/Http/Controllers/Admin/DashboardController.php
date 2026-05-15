<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Detection;
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

        // 2. Laporan Per Desa
        $reportsPerVillage = DB::table('detections')
            ->join('users', 'detections.user_id', '=', 'users.id')
            ->join('villages', 'users.village_id', '=', 'villages.id')
            ->select('villages.village_name', DB::raw('count(detections.id) as total_reports'))
            ->groupBy('villages.id', 'villages.village_name')
            ->get();

        // 3. Laporan Jenis Hama Per Desa
        $pestPerVillage = DB::table('detection_results')
            ->join('detections', 'detection_results.detection_id', '=', 'detections.id')
            ->join('users', 'detections.user_id', '=', 'users.id')
            ->join('villages', 'users.village_id', '=', 'villages.id')
            ->select(
                'villages.village_name',
                'detection_results.pest_name',
                DB::raw('count(detection_results.id) as total')
            )
            ->groupBy('villages.id', 'villages.village_name', 'detection_results.pest_name')
            ->get();

        // Format data hama agar lebih mudah dibaca oleh Frontend:
        // { "Desa A": { "Wereng Cokelat": 2, "Kutu Daun": 1 } }
        $formattedPestPerVillage = [];
        foreach ($pestPerVillage as $item) {
            if (!isset($formattedPestPerVillage[$item->village_name])) {
                $formattedPestPerVillage[$item->village_name] = [];
            }
            $formattedPestPerVillage[$item->village_name][$item->pest_name] = $item->total;
        }

        return response()->json([
            'overview' => [
                'total_reports' => $totalDetections,
                'pending' => $pendingDetections,
                'completed' => $completedDetections,
            ],
            'reports_per_village' => $reportsPerVillage,
            'pests_per_village' => $formattedPestPerVillage
        ]);
    }
}
