<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\Recommendation;

class PenyuluhController extends Controller
{
    // Lihat daftar semua penyuluh
    public function index()
    {
        $penyuluhRole = Role::where('name', 'penyuluh')->first();
        
        if (!$penyuluhRole) {
            return response()->json(['message' => 'Role penyuluh tidak ditemukan'], 404);
        }

        $penyuluhs = User::where('role_id', $penyuluhRole->id)->with('managedVillages')->get();

        return response()->json($penyuluhs);
    }

    // Tambah akun penyuluh + pasangkan desa-desanya
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|unique:users,username|max:255',
            'email' => 'required|string|email|unique:users,email|max:255',
            'password' => 'required|string|min:6',
            'no_hp' => 'nullable|string|max:20',
            'villages' => 'required|array', // array of village IDs
            'villages.*' => 'exists:villages,id'
        ]);

        // Cek apakah ada desa yang sudah memiliki penyuluh
        if (!empty($request->villages)) {
            $assignedVillages = \App\Models\Village::whereIn('id', $request->villages)
                                ->whereNotNull('penyuluh_id')
                                ->get();
                                
            if ($assignedVillages->isNotEmpty()) {
                $names = $assignedVillages->pluck('village_name')->join(', ');
                return response()->json([
                    'message' => "Gagal: Desa {$names} sudah ditugaskan ke penyuluh lain. Satu desa hanya boleh memiliki satu penyuluh."
                ], 422);
            }
        }

        $penyuluhRole = Role::where('name', 'penyuluh')->first();

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'no_hp' => $request->no_hp,
            'role_id' => $penyuluhRole->id,
        ]);

        // Hubungkan si penyuluh ini ke desa-desa yang dipilih
        if (!empty($request->villages)) {
            \App\Models\Village::whereIn('id', $request->villages)->update(['penyuluh_id' => $user->id]);
        }

        return response()->json([
            'message' => 'Akun penyuluh berhasil ditambahkan.',
            'penyuluh' => $user->load('managedVillages')
        ], 201);
    }

    // Edit profil dan desa tanggungan penyuluh
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'username' => 'sometimes|required|string|max:255|unique:users,username,'.$user->id,
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,'.$user->id,
            'no_hp' => 'nullable|string|max:20',
            'villages' => 'present|array',
            'villages.*' => 'exists:villages,id'
        ]);

        // Cek apakah ada desa pilihan baru yang sudah memiliki penyuluh (selain milik penyuluh ini sendiri)
        if (!empty($request->villages)) {
            $assignedVillages = \App\Models\Village::whereIn('id', $request->villages)
                                ->whereNotNull('penyuluh_id')
                                ->where('penyuluh_id', '!=', $user->id)
                                ->get();

            if ($assignedVillages->isNotEmpty()) {
                $names = $assignedVillages->pluck('village_name')->join(', ');
                return response()->json([
                    'message' => "Gagal: Desa {$names} sudah ditugaskan ke penyuluh lain."
                ], 422);
            }
        }

        // Update data profil
        $user->update($request->only(['name', 'username', 'email', 'no_hp']));

        // Langsung update/sync desa yang dipegang
        // Reset desa lama
        \App\Models\Village::where('penyuluh_id', $user->id)->update(['penyuluh_id' => null]);
        
        // Pasang desa baru
        if (!empty($request->villages)) {
            \App\Models\Village::whereIn('id', $request->villages)->update(['penyuluh_id' => $user->id]);
        }

        return response()->json([
            'message' => 'Data dan desa tanggungan penyuluh berhasil diupdate.',
            'penyuluh' => $user->load('managedVillages')
        ]);
    }

    // Hapus akun penyuluh
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        // Cek apakah penyuluh ini pernah ngasih rekomendasi
        $hasRecommendations = Recommendation::where('created_by', $user->id)->exists();
        if ($hasRecommendations) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akun penyuluh tidak dapat dihapus karena sudah memiliki riwayat pemberian rekomendasi pada deteksi petani.'
            ], 400); 
        }

        $user->delete();

        return response()->json([
            'message' => 'Akun penyuluh berhasil dihapus.'
        ]);
    }
}
