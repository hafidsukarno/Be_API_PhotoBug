<?php

namespace App\Http\Controllers\Petani;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class VillageController extends Controller
{
    /**
     * Get village data untuk petani yang login
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request)
    {
        $user = $request->user();
        
        // Check if user has village
        if (!$user->village_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pengguna tidak terdaftar di desa manapun'
            ], 404);
        }

        $village = $user->village;

        return response()->json([
            'status' => 'success',
            'message' => 'Data desa petani',
            'data' => [
                'id' => $village->id,
                'village_name' => $village->village_name,
                'district' => $village->district,
            ]
        ]);
    }
}
