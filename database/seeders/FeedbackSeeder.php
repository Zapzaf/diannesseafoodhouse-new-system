<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Feedback;
use Illuminate\Database\Seeder;

class FeedbackSeeder extends Seeder
{
    /**
     * Assign feedback without a branch to the Carcar Branch.
     */
    public function run(): void
    {
        $branch = Branch::query()->where('name', 'Carcar Branch')->firstOrFail();

        Feedback::query()
            ->whereNull('branch_id')
            ->update(['branch_id' => $branch->id]);
    }
}
