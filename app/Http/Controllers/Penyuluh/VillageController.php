<?php

namespace App\Http\Controllers\Penyuluh;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class VillageController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Ambil data desa binaan dan ambil nama desanya saja
        $managedVillages = $user->managedVillages->pluck('village_name');

        return response()->json([
            'status' => 'success',
            'data' => $managedVillages
        ]);
    }
}
