<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        Branch::updateOrCreate(
            ['name' => 'Carcar Branch'],
            [
                'address' => 'General Luna St., Poblacion 3, Carcar City, Cebu',
                'is_active' => true,
            ]
        );

        Branch::updateOrCreate(
            ['name' => 'Mandaue Branch'],
            [
                'address' => 'C.M.Cabahug, Cambaro, Mandaue, 6014 Cebu',
                'is_active' => true,
            ]
        );
    }
}
