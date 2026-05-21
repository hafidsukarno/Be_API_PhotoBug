<?php

namespace App\Http\Controllers\Penyuluh;

use App\Http\Controllers\Controller;
use App\Models\Detection;
use App\Models\Recommendation;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    // Memberikan rekomendasi balik ke petani
    public function store(Request $request, $id)
    {
        $user = $request->user();
        $detection = Detection::findOrFail($id);

        // Verifikasi bahwa petani yang di-report ada di desa yang ditangani penyuluh
        $villageIds = $user->managedVillages()->pluck('id');
        $petaniVillageId = $detection->user->village_id;

        if (!$villageIds->contains($petaniVillageId)) {
            return response()->json(['message' => 'Unauthorized. Laporan ini bukan dari desa yang Anda tangani.'], 403);
        }

        $request->validate([
            'recommendation_text' => 'required|string'
        ]);

        // Buat rekomendasi dari penyuluh
        $recommendation = Recommendation::create([
            'detection_id' => $detection->id,
            'recommendation_text' => $request->recommendation_text,
            'source' => 'penyuluh',
            'created_by' => $user->id
        ]);

        // Update status deteksi menjadi 'completed'
        $detection->update(['status' => 'completed']);

        return response()->json([
            'message' => 'Rekomendasi berhasil diberikan. Status laporan diubah menjadi selesai.',
            'recommendation' => $recommendation->load('createdBy')
        ], 201);
    }
}
