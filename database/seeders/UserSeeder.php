<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $carcarBranch = Branch::where('name', 'Carcar Branch')->firstOrFail();
        $mandaueBranch = Branch::where('name', 'Mandaue Branch')->firstOrFail();

        $admin = User::updateOrCreate(
            ['email' => 'admin@diannesseafoodhouse.com'],
            [
                'name' => 'System Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'branch_id' => null,
                'phone' => '09170000001',
                'email_verified_at' => now(),
            ]
        );

        $carcarManager = User::updateOrCreate(
            ['email' => 'manager.carcar@diannesseafoodhouse.com'],
            [
                'name' => 'Carcar Branch Manager',
                'password' => Hash::make('password'),
                'role' => 'branch_manager',
                'branch_id' => $carcarBranch->id,
                'phone' => '09170000011',
                'email_verified_at' => now(),
            ]
        );

        $mandaueManager = User::updateOrCreate(
            ['email' => 'manager.mandaue@diannesseafoodhouse.com'],
            [
                'name' => 'Mandaue Branch Manager',
                'password' => Hash::make('password'),
                'role' => 'branch_manager',
                'branch_id' => $mandaueBranch->id,
                'phone' => '09170000012',
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'staff.carcar@diannesseafoodhouse.com'],
            [
                'name' => 'Carcar Branch Staff',
                'password' => Hash::make('password'),
                'role' => 'regular_user',
                'branch_id' => $carcarBranch->id,
                'phone' => '09170000101',
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'staff.mandaue@diannesseafoodhouse.com'],
            [
                'name' => 'Mandaue Branch Staff',
                'password' => Hash::make('password'),
                'role' => 'regular_user',
                'branch_id' => $mandaueBranch->id,
                'phone' => '09170000102',
                'email_verified_at' => now(),
            ]
        );

        $carcarBranch->update(['manager_id' => $carcarManager->id]);
        $mandaueBranch->update(['manager_id' => $mandaueManager->id]);

        // Keep a fallback test user for simple local login checks.
        User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'role' => 'regular_user',
                'branch_id' => $carcarBranch->id,
                'phone' => '09170000999',
                'email_verified_at' => now(),
            ]
        );
    }
}
