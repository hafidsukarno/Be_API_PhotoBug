<?php

namespace App\Http\Controllers;

use App\Models\Village;
use Illuminate\Http\Request;

class PublicController extends Controller
{
    /**
     * Get semua village untuk keperluan registrasi (PUBLIC)
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getVillagesForRegistration()
    {
        $villages = Village::select('id', 'village_name', 'district')->get();

        return response()->json([
            'status' => 'success',
            'total_villages' => count($villages),
            'message' => 'Daftar desa untuk registrasi',
            'data' => $villages
        ]);
    }
}
