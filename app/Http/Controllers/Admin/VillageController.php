<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Village;
use App\Models\User;
use Illuminate\Http\Request;

class VillageController extends Controller
{
    public function index()
    {
        return response()->json(Village::all());
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
