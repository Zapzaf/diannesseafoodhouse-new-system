<?php

use App\Models\Branch;
use App\Models\PosTerminal;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * One-time data backfill: any branch that predates POS terminal
     * auto-provisioning (or otherwise still has zero terminals) gets a
     * default "Register 1" terminal, so Cash Shifts / X,Y,Z Reading work
     * immediately without a manual terminal-setup step.
     */
    public function up(): void
    {
        $branchIdsWithTerminals = PosTerminal::query()->pluck('branch_id')->unique();

        Branch::query()
            ->whereNotIn('id', $branchIdsWithTerminals)
            ->get()
            ->each(function (Branch $branch): void {
                PosTerminal::ensureDefaultForBranch($branch->id);
            });
    }

    /**
     * Intentionally irreversible: removing auto-provisioned terminals could
     * delete ones already in active use for cash shifts / Z Readings.
     */
    public function down(): void
    {
    }
};
