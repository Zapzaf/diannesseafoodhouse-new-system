<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $creator = User::where('role', 'admin')->firstOrFail();

        $suppliers = [
            [
                'name' => 'Pacific Fresh Catch Trading',
                'contact_person' => 'Mario Santos',
                'phone' => '09181234567',
                'email' => 'orders@pacificfresh.local',
                'address' => 'Navotas Fish Port, Metro Manila',
                'notes' => 'Daily seafood supplier.',
            ],
            [
                'name' => 'Island Poultry & Meat Supply',
                'contact_person' => 'Elena Cruz',
                'phone' => '09182345678',
                'email' => 'sales@islandpoultry.local',
                'address' => 'Malabon, Metro Manila',
                'notes' => 'Chicken and pork wholesale.',
            ],
            [
                'name' => 'Golden Pantry Goods',
                'contact_person' => 'Rico Dela Pena',
                'phone' => '09183456789',
                'email' => 'support@goldenpantry.local',
                'address' => 'Quezon City, Metro Manila',
                'notes' => 'Dry and canned goods.',
            ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::updateOrCreate(
                ['name' => $supplier['name']],
                [
                    ...$supplier,
                    'created_by' => $creator->id,
                ]
            );
        }
    }
}
