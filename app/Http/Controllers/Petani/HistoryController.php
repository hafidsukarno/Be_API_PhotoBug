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

        $query = Detection::where('user_id', $user->id)->with('detectionResults', 'recommendations.createdBy')->orderBy('detected_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        $detections = $query->get();

        return response()->json([
            'total' => count($detections),
            'pending_count' => Detection::where('user_id', $user->id)->where('status', 'pending')->count(),
            'completed_count' => Detection::where('user_id', $user->id)->where('status', 'completed')->count(),
            'data' => $detections
        ]);
    }
}
