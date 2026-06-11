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
        ];

        foreach ($villages as $village) {
            Village::firstOrCreate($village);
        }
    }
}
