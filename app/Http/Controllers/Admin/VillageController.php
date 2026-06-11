<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Village;
use App\Models\User;
use App\Models\Detection;
use App\Models\DetectionResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VillageController extends Controller
{
    public function index()
    {
        $villages = Village::with('penyuluh')->get();

        return response()->json([
            'status' => 'success',
            'total_villages' => count($villages),
            'data' => $villages->map(function ($village) {
                return [
                    'id' => $village->id,
                    'village_name' => $village->village_name,
                    'district' => $village->district
                ];
            })
        ]);
    }

    // Get desa dengan statistik laporan dan hama
    public function getVillagesReport()
    {
        $villages = Village::with('penyuluh')->get();

        $villagesTotalStats = $villages->map(function ($village) {
            $userIds = \App\Models\User::where('village_id', $village->id)->pluck('id');

            // Total laporan di desa ini
            $totalReports = Detection::whereIn('user_id', $userIds)->count();

            // Total hama yang terdeteksi (jumlah, bukan jenis)
            $totalPests = DetectionResult::whereHas('detection', function ($query) use ($userIds) {
                $query->whereIn('user_id', $userIds);
            })->count();

            return [
                'id' => $village->id,
                'village_name' => $village->village_name,
                'district' => $village->district,
                'penyuluh_name' => $village->penyuluh?->name ?? 'Belum Ditugaskan',
                'penyuluh_id' => $village->penyuluh_id,
                'total_reports' => $totalReports,
                'total_pests_detected' => $totalPests
            ];
        });

        return response()->json([
            'status' => 'success',
            'total_villages' => count($villagesTotalStats),
            'data' => $villagesTotalStats
        ]);
    }

    public function getVillagesStatus()
    {
        $villages = Village::all();

        return response()->json([
            'status' => 'success',
            'data' => $villages->map(function ($village) {
                return [
                    'village_name' => $village->village_name,
                    'status' => $village->penyuluh_id ? 'terisi' : 'kosong'
                ];
            })
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'village_name' => 'required|string|max:255',
            'district' => 'required|string|max:255',
        ]);

        $village = Village::create([
            'village_name' => $request->village_name,
            'district' => $request->district,
        ]);

        return response()->json([
            'message' => 'Desa berhasil ditambahkan.',
            'village' => $village
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $village = Village::findOrFail($id);

        $request->validate([
            'village_name' => 'sometimes|string|max:255',
            'district' => 'sometimes|string|max:255',
        ]);

        $village->update($request->only(['village_name', 'district']));

        return response()->json([
            'message' => 'Desa berhasil diupdate.',
            'village' => $village
        ]);
    }

    public function destroy($id)
    {
        $village = Village::findOrFail($id);

        // Cek apakah ada user (Petani) yang terdaftar di desa ini
        $hasUsers = User::where('village_id', $village->id)->exists();
        if ($hasUsers) {
            return response()->json([
                'status' => 'error',
                'message' => 'Desa tidak dapat dihapus karena masih ada pengguna (Petani) yang terdaftar di desa ini.'
            ], 400);
        }

        $village->delete();

        return response()->json([
            'message' => 'Desa berhasil dihapus.'
        ]);
    }
}
