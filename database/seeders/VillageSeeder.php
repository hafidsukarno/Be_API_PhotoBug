<?php

namespace Database\Seeders;

use App\Models\Village;
use Illuminate\Database\Seeder;

class VillageSeeder extends Seeder
{
    public function run(): void
    {
        $villages = [
            ['village_name' => 'Sariwangi', 'district' => 'Sariwangi'],
            ['village_name' => 'Cibodas', 'district' => 'Lembang'],
            ['village_name' => 'Sukaratu', 'district' => 'Singaparna'],
            ['village_name' => 'Cisurupan', 'district' => 'Cisurupan'],
        ];

        foreach ($villages as $village) {
            Village::firstOrCreate($village);
        }
    }
}
