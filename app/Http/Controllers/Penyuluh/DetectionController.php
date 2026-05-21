<?php

namespace App\Http\Controllers\Penyuluh;

use App\Http\Controllers\Controller;
use App\Models\Detection;
use App\Models\DetectionResult;
use Illuminate\Http\Request;
use Carbon\Carbon;

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
        ->with('user.village', 'detectionResults', 'recommendations.createdBy')
        ->orderByRaw("CASE WHEN status = 'pending' THEN 1 ELSE 2 END")
        ->orderBy('detected_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        $detections = $query->get();

        $formattedDetections = $detections->map(function ($detection) {
            $topDetection = $detection->detectionResults->first();
            return [
                'id' => $detection->id,
                'image_path' => $detection->image_path,
                'petani_name' => $detection->user->name ?? 'Unknown',
                'village_name' => $detection->user->village->village_name ?? 'Unknown',
                'pest_name' => $topDetection ? $topDetection->pest_name : 'Tidak terdeteksi',
                'confidence' => $topDetection ? $topDetection->confidence : '0',
                'status' => $detection->status,
                'detected_at' => $detection->detected_at
            ];
        });

        return response()->json([
            'total' => count($detections),
            'pending_count' => Detection::whereIn('user_id', function ($q) use ($villageIds) {
                $q->select('id')->from('users')->whereIn('village_id', $villageIds);
            })->where('status', 'pending')->count(),
            'completed_count' => Detection::whereIn('user_id', function ($q) use ($villageIds) {
                $q->select('id')->from('users')->whereIn('village_id', $villageIds);
            })->where('status', 'completed')->count(),
            'data' => $formattedDetections
        ]);
    }

    // Lihat detail deteksi tertentu
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $detection = Detection::with('user.village', 'detectionResults', 'recommendations.createdBy')->findOrFail($id);

        // Verifikasi bahwa petani yang di-report ada di desa yang ditangani penyuluh
        $villageIds = $user->managedVillages()->pluck('id');
        $petaniVillageId = $detection->user->village_id;

        if (!$villageIds->contains($petaniVillageId)) {
            return response()->json(['message' => 'Unauthorized. Laporan ini bukan dari desa yang Anda tangani.'], 403);
        }

        $topDetection = $detection->detectionResults->first();
        $aiRecommendation = $detection->recommendations->where('source', 'AI')->first();
        $penyuluhRecommendation = $detection->recommendations->where('source', 'penyuluh')->first();

        $formattedData = [
            'id' => $detection->id,
            'petani_name' => $detection->user->name ?? 'Unknown',
            'petani_email' => $detection->user->email ?? 'Unknown',
            'village_name' => $detection->user->village->village_name ?? 'Unknown',
            'description' => $detection->description,
            'image_path' => $detection->image_path,
            'pest_name' => $topDetection ? $topDetection->pest_name : 'Tidak terdeteksi',
            'highest_confidence' => $topDetection ? $topDetection->confidence : '0',
            'pest_count' => $detection->detectionResults->count(),
            'ai_recommendation' => $aiRecommendation ? $aiRecommendation->recommendation_text : null,
            'penyuluh_recommendation' => $penyuluhRecommendation ? $penyuluhRecommendation->recommendation_text : null,
            'status' => $detection->status,
            'detected_at' => $detection->detected_at
        ];

        return response()->json($formattedData);
    }

    // Status laporan untuk penyuluh
    public function reportStatus(Request $request)
    {
        $user = $request->user();

        // Dapatkan desa-desa yang ditangani penyuluh ini
        $villageIds = $user->managedVillages()->pluck('id');

        if ($villageIds->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'total_incoming' => 0,
                    'waiting_verification' => 0,
                    'completed' => 0
                ]
            ]);
        }

        // Total laporan masuk dari desa yang ditangani
        $totalIncoming = Detection::whereIn('user_id', function ($q) use ($villageIds) {
            $q->select('id')->from('users')->whereIn('village_id', $villageIds);
        })->count();

        // Laporan menunggu (pending - belum ada rekomendasi penyuluh)
        $waiting = Detection::whereIn('user_id', function ($q) use ($villageIds) {
            $q->select('id')->from('users')->whereIn('village_id', $villageIds);
        })->where('status', 'pending')->count();

        // Laporan selesai (completed - sudah ada rekomendasi dari penyuluh)
        $completed = Detection::whereIn('user_id', function ($q) use ($villageIds) {
            $q->select('id')->from('users')->whereIn('village_id', $villageIds);
        })->where('status', 'completed')->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_incoming' => $totalIncoming,
                'waiting_verification' => $waiting,
                'completed' => $completed
            ]
        ]);
    }

    // Tren deteksi hama dalam 6 bulan terakhir
    public function pestTrend(Request $request)
    {
        $user = $request->user();

        // Dapatkan desa-desa yang ditangani penyuluh ini
        $villageIds = $user->managedVillages()->pluck('id');

        if ($villageIds->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Penyuluh ini tidak menangani desa apapun',
                'data' => []
            ]);
        }

        // 6 bulan terakhir
        $sixMonthsAgo = Carbon::now()->subMonths(6);

        // Query detection results dari desa yang ditangani dalam 6 bulan terakhir
        $pestTrends = DetectionResult::whereHas('detection', function ($query) use ($villageIds, $sixMonthsAgo) {
            $query->whereIn('user_id', function ($q) use ($villageIds) {
                $q->select('id')->from('users')->whereIn('village_id', $villageIds);
            })
            ->where('detected_at', '>=', $sixMonthsAgo);
        })
        ->selectRaw('pest_name, COUNT(*) as total_detected')
        ->groupBy('pest_name')
        ->orderByRaw('total_detected DESC')
        ->get();

        // Initialize default pests
        $defaultPests = [
            'wereng coklat' => 0,
            'wereng hijau' => 0
        ];

        // Fill with actual data
        foreach ($pestTrends as $item) {
            $defaultPests[$item->pest_name] = $item->total_detected;
        }

        // Format data to array
        $formattedData = [];
        foreach ($defaultPests as $name => $total) {
            $formattedData[] = [
                'pest_name' => $name,
                'total_detected' => $total
            ];
        }

        // Sort by total_detected DESC
        usort($formattedData, function ($a, $b) {
            return $b['total_detected'] <=> $a['total_detected'];
        });

        return response()->json([
            'status' => 'success',
            'period' => [
                'from' => $sixMonthsAgo->format('Y-m-d'),
                'to' => Carbon::now()->format('Y-m-d')
            ],
            'data' => $formattedData
        ]);
    }

    // Get notifikasi laporan masuk untuk penyuluh
    public function getNotifications(Request $request)
    {
        $user = $request->user();
        $page = $request->query('page', 1);
        $perPage = $request->query('per_page', 10);

        // Dapatkan desa-desa yang ditangani penyuluh ini
        $villageIds = $user->managedVillages()->pluck('id');

        if ($villageIds->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Penyuluh ini tidak menangani desa apapun',
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => 0,
                    'last_page' => 0
                ],
                'data' => []
            ]);
        }

        // Query laporan masuk dari desa yang ditangani, dengan detail lengkap
        $total = Detection::whereIn('user_id', function ($q) use ($villageIds) {
            $q->select('id')->from('users')->whereIn('village_id', $villageIds);
        })->count();

        $notifications = Detection::whereIn('user_id', function ($q) use ($villageIds) {
            $q->select('id')->from('users')->whereIn('village_id', $villageIds);
        })
        ->with('user', 'user.village', 'detectionResults')
        ->orderBy('created_at', 'desc')
        ->skip(($page - 1) * $perPage)
        ->take($perPage)
        ->get();

        // Format notifikasi menjadi ringkas
        $formattedNotifications = $notifications->map(function ($detection) {
            // Ambil hasil deteksi AI (pest_name dan confidence tertinggi)
            $topDetection = $detection->detectionResults->first();
            $pestName = $topDetection ? $topDetection->pest_name : 'Tidak Terdeteksi';
            $confidence = $topDetection ? round($topDetection->confidence * 100, 2) : 0;

            return [
                'timestamp_ago' => $detection->created_at->diffForHumans(),
                'petani_name' => $detection->user->name,
                'village_name' => $detection->user->village?->village_name ?? 'Tidak ada desa',
                'detected_pest' => $pestName,
                'ai_confidence' => $confidence . '%'
            ];
        });

        return response()->json([
            'status' => 'success',
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage)
            ],
            'data' => $formattedNotifications
        ]);
    }
}
