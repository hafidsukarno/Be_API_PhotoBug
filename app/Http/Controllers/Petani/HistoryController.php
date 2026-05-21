<?php

namespace App\Http\Controllers\Petani;

use App\Http\Controllers\Controller;
use App\Models\Detection;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    // Lihat riwayat deteksi petani dengan filter status
    public function index(Request $request)
    {
        $user = $request->user();
        $status = $request->query('status'); // 'pending', 'completed', atau null (semua)

        $query = Detection::where('user_id', $user->id)
            ->with('detectionResults', 'recommendations.createdBy', 'user.village.penyuluh')
            ->orderBy('detected_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        $detections = $query->get();

        // Transform data jadi lebih simple
        $simplifiedData = $detections->map(function ($detection) {
            // Cari akurasi tertinggi dari detection_results
            $highestConfidence = 0;
            $pestName = '';
            if ($detection->detectionResults->count() > 0) {
                $topResult = $detection->detectionResults->sortByDesc('confidence')->first();
                $highestConfidence = round($topResult->confidence * 100, 1);
                $pestName = $topResult->pest_name;
            }

            // Cari rekomendasi AI
            $aiRecommendation = 'Menunggu analisis AI';
            if ($detection->recommendations->count() > 0) {
                $aiRec = $detection->recommendations->where('source', 'AI')->first();
                if ($aiRec) {
                    $aiRecommendation = $aiRec->recommendation_text;
                }
            }

            // Cari rekomendasi Penyuluh (is_validated = 1)
            $penyuluhRecommendation = 'Menunggu rekomendasi penyuluh';
            if ($detection->recommendations->count() > 0) {
                $validatedRec = $detection->recommendations->where('is_validated', 1)->first();
                if ($validatedRec) {
                    $penyuluhRecommendation = $validatedRec->recommendation_text;
                }
            }

            // Ambil nama penyuluh
            $penyuluhName = 'Belum Ditugaskan';
            if ($detection->user && $detection->user->village && $detection->user->village->penyuluh) {
                $penyuluhName = $detection->user->village->penyuluh->name;
            }

            // Ambil nama desa
            $villageName = 'Tidak diketahui';
            if ($detection->user && $detection->user->village) {
                $villageName = $detection->user->village->village_name;
            }

            return [
                'id' => $detection->id,
                'user_id' => $detection->user_id,
                'image_path' => $detection->image_path,
                'village_name' => $villageName,
                'penyuluh_name' => $penyuluhName,
                'status' => $detection->status,
                'detected_at' => $detection->detected_at,
                'pest_name' => $pestName,
                'highest_confidence' => $highestConfidence . '%',
                'ai_recommendation' => $aiRecommendation,
                'penyuluh_recommendation' => $penyuluhRecommendation
            ];
        });

        return response()->json([
            'total' => count($simplifiedData),
            'pending_count' => Detection::where('user_id', $user->id)->where('status', 'pending')->count(),
            'completed_count' => Detection::where('user_id', $user->id)->where('status', 'completed')->count(),
            'data' => $simplifiedData
        ]);
    }
}
