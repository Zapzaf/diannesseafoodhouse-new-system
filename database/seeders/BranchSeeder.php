<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        Branch::updateOrCreate(
            ['name' => 'Main Branch'],
            [
                'address' => 'Dianne\'s Seafood House - Main Branch',
                'is_active' => true,
            ]
        );

        Branch::updateOrCreate(
            ['name' => 'North Branch'],
            [
                'address' => 'Dianne\'s Seafood House - North Branch',
                'is_active' => true,
            ]
        );

        Branch::updateOrCreate(
            ['name' => 'South Branch'],
            [
                'address' => 'Dianne\'s Seafood House - South Branch',
                'is_active' => true,
            ]
        );
    }
}
