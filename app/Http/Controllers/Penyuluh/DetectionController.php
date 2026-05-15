<?php

namespace App\Http\Controllers\Penyuluh;

use App\Http\Controllers\Controller;
use App\Models\Detection;
use Illuminate\Http\Request;

class DetectionController extends Controller
{
    // Lihat laporan deteksi dari petani di desa yang ditangani penyuluh
    public function index(Request $request)
    {
        $user = $request->user();
        $status = $request->query('status'); // 'pending', 'completed', atau null (semua)

        // Dapatkan desa-desa yang ditangani penyuluh ini
        $villageIds = $user->managedVillages()->pluck('id');

        if ($villageIds->isEmpty()) {
            return response()->json([
                'message' => 'Penyuluh ini tidak menangani desa apapun',
                'data' => []
            ]);
        }

        // Query deteksi dari petani di desa yang ditangani
        $query = Detection::whereIn('user_id', function ($q) use ($villageIds) {
            $q->select('id')
                ->from('users')
                ->whereIn('village_id', $villageIds);
        })
        ->with('user', 'detectionResults', 'recommendations.createdBy')
        ->orderBy('detected_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        $detections = $query->get();

        return response()->json([
            'total' => count($detections),
            'pending_count' => Detection::whereIn('user_id', function ($q) use ($villageIds) {
                $q->select('id')->from('users')->whereIn('village_id', $villageIds);
            })->where('status', 'pending')->count(),
            'completed_count' => Detection::whereIn('user_id', function ($q) use ($villageIds) {
                $q->select('id')->from('users')->whereIn('village_id', $villageIds);
            })->where('status', 'completed')->count(),
            'data' => $detections
        ]);
    }

    // Lihat detail deteksi tertentu
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $detection = Detection::with('user', 'detectionResults', 'recommendations.createdBy')->findOrFail($id);

        // Verifikasi bahwa petani yang di-report ada di desa yang ditangani penyuluh
        $villageIds = $user->managedVillages()->pluck('id');
        $petaniVillageId = $detection->user->village_id;

        if (!$villageIds->contains($petaniVillageId)) {
            return response()->json(['message' => 'Unauthorized. Laporan ini bukan dari desa yang Anda tangani.'], 403);
        }

        return response()->json($detection);
    }
}
