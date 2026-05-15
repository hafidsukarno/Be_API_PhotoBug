<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('name', 'admin')->first();
        $penyuluhRole = Role::where('name', 'penyuluh')->first();
        $petaniRole = Role::where('name', 'petani')->first();

        // 1 Admin
        User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Super Admin',
                'username' => 'superadmin',
                'password' => Hash::make('password123'),
                'role_id' => $adminRole->id,
            ]
        );

        $village1 = \App\Models\Village::where('village_name', 'Sariwangi')->first();
        $village2 = \App\Models\Village::where('village_name', 'Cibodas')->first();

        // 2 Penyuluh
        $hafid = User::firstOrCreate(
            ['email' => 'hafid@penyuluh.com'],
            [
                'name' => 'Hafid',
                'username' => 'hafid',
                'password' => Hash::make('password123'),
                'role_id' => $penyuluhRole->id,
            ]
        );

        $adel = User::firstOrCreate(
            ['email' => 'adel@penyuluh.com'],
            [
                'name' => 'Adel',
                'username' => 'adel',
                'password' => Hash::make('password123'),
                'role_id' => $penyuluhRole->id,
            ]
        );

        // Tugaskan desa ke penyuluh
        if ($village1) $village1->update(['penyuluh_id' => $hafid->id]);
        if ($village2) $village2->update(['penyuluh_id' => $adel->id]);

        // 2 Petani
        if ($petaniRole) {
            User::firstOrCreate(
                ['email' => 'daniel@petani.com'],
                [
                    'name' => 'Daniel',
                    'username' => 'daniel',
                    'password' => Hash::make('password123'),
                    'role_id' => $petaniRole->id,
                    'village_id' => $village1->id ?? null,
                ]
            );

            User::firstOrCreate(
                ['email' => 'nano@petani.com'],
                [
                    'name' => 'Nano',
                    'username' => 'nano',
                    'password' => Hash::make('password123'),
                    'role_id' => $petaniRole->id,
                    'village_id' => $village2->id ?? null,
                ]
            );
        }
    }
}
