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
        $mainBranch = Branch::where('name', 'Main Branch')->firstOrFail();
        $northBranch = Branch::where('name', 'North Branch')->firstOrFail();
        $southBranch = Branch::where('name', 'South Branch')->firstOrFail();

        $admin = User::updateOrCreate(
            ['email' => 'admin@diannesseafoodhouse.com'],
            [
                'name' => 'System Admin',
                'password' => Hash::make('Akosigian2025!'),
                'role' => 'admin',
                'branch_id' => null,
                'phone' => '09170000001',
                'email_verified_at' => now(),
            ]
        );

        $mainManager = User::updateOrCreate(
            ['email' => 'manager.main@diannesseafoodhouse.com'],
            [
                'name' => 'Main Branch Manager',
                'password' => Hash::make('password'),
                'role' => 'branch_manager',
                'branch_id' => $mainBranch->id,
                'phone' => '09170000011',
                'email_verified_at' => now(),
            ]
        );

        $northManager = User::updateOrCreate(
            ['email' => 'manager.north@diannesseafoodhouse.com'],
            [
                'name' => 'North Branch Manager',
                'password' => Hash::make('password'),
                'role' => 'branch_manager',
                'branch_id' => $northBranch->id,
                'phone' => '09170000012',
                'email_verified_at' => now(),
            ]
        );

        $southManager = User::updateOrCreate(
            ['email' => 'manager.south@diannesseafoodhouse.com'],
            [
                'name' => 'South Branch Manager',
                'password' => Hash::make('password'),
                'role' => 'branch_manager',
                'branch_id' => $southBranch->id,
                'phone' => '09170000013',
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'staff.main@diannesseafoodhouse.com'],
            [
                'name' => 'Main Branch Staff',
                'password' => Hash::make('password'),
                'role' => 'regular_user',
                'branch_id' => $mainBranch->id,
                'phone' => '09170000101',
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'staff.north@diannesseafood.local'],
            [
                'name' => 'North Branch Staff',
                'password' => Hash::make('password'),
                'role' => 'regular_user',
                'branch_id' => $northBranch->id,
                'phone' => '09170000102',
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'staff.south@diannesseafood.local'],
            [
                'name' => 'South Branch Staff',
                'password' => Hash::make('password'),
                'role' => 'regular_user',
                'branch_id' => $southBranch->id,
                'phone' => '09170000103',
                'email_verified_at' => now(),
            ]
        );

        $mainBranch->update(['manager_id' => $mainManager->id]);
        $northBranch->update(['manager_id' => $northManager->id]);
        $southBranch->update(['manager_id' => $southManager->id]);

        // Keep a fallback test user for simple local login checks.
        User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'role' => 'regular_user',
                'branch_id' => $mainBranch->id,
                'phone' => '09170000999',
                'email_verified_at' => now(),
            ]
        );
    }
}
