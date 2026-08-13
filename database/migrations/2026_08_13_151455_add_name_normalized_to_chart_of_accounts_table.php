<?php

use App\Support\NameNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs duplicate detection for Chart of Accounts names: a stored,
     * always-in-sync (see ChartOfAccount::booted()) lowercase/singularized
     * comparison key, so "Meal Expense" / "MEAL EXPENSE" / "Meals Expense"
     * / "meal expenses" all collide as the same account instead of only
     * exact byte-for-byte matches (which the old name-unique index caught).
     */
    public function up(): void
    {
        // MySQL DDL isn't transactional, so a prior failed run (e.g. the
        // duplicate check below throwing) can leave the column already
        // added — guard each step so this migration is safe to re-run.
        if (! Schema::hasColumn('chart_of_accounts', 'name_normalized')) {
            Schema::table('chart_of_accounts', function (Blueprint $table) {
                $table->string('name_normalized')->nullable()->after('name');
            });
        }

        DB::table('chart_of_accounts')->select('id', 'name')->orderBy('id')->each(function ($row): void {
            DB::table('chart_of_accounts')->where('id', $row->id)->update([
                'name_normalized' => NameNormalizer::normalize($row->name),
            ]);
        });

        // If any existing accounts already normalize to the same value (e.g.
        // "Meal Expense" and "Meal Expenses" both already exist as separate
        // rows), fail loudly here rather than let the unique index below
        // throw a cryptic duplicate-key error — those need to be manually
        // renamed/merged before this migration can proceed.
        $duplicates = DB::table('chart_of_accounts')
            ->select('name_normalized')
            ->groupBy('name_normalized')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name_normalized');

        if ($duplicates->isNotEmpty()) {
            throw new \RuntimeException(
                'Cannot add a unique index on chart_of_accounts.name_normalized — these existing '
                .'accounts normalize to the same name and must be renamed or merged first: '
                .$duplicates->implode(', ')
            );
        }

        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->string('name_normalized')->nullable(false)->change();
            $table->unique('name_normalized');
        });
    }

    public function down(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropUnique(['name_normalized']);
            $table->dropColumn('name_normalized');
        });
    }
};
