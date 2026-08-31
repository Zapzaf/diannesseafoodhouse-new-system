<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A VIRTUAL generated column that is NULL unless the reading is
     * currently 'locked', in which case it's "{terminal}-{date}". MySQL
     * treats NULLs as distinct for unique indexes, so this lets many voided
     * readings share a terminal+date while still guaranteeing at most one
     * *locked* Z Reading per terminal per business day — enforced by the
     * database itself, not just application-level checks (which can race
     * when no row exists yet to lock). VIRTUAL (not STORED) is used because
     * InnoDB refuses to add a STORED generated column derived from a
     * foreign-keyed column on this table (MySQL error 1215).
     */
    public function up(): void
    {
        // CONCAT() is MySQL-only; SQLite (used for the test suite) needs the
        // '||' concatenation operator for the same expression instead.
        $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';
        $concat = $isSqlite
            ? "pos_terminal_id || '-' || business_date"
            : "CONCAT(pos_terminal_id, '-', business_date)";

        Schema::table('z_readings', function (Blueprint $table) use ($concat) {
            $table->string('active_lock_key', 150)
                ->nullable()
                ->virtualAs("CASE WHEN status = 'locked' THEN {$concat} ELSE NULL END")
                ->after('status');
        });

        Schema::table('z_readings', function (Blueprint $table) {
            $table->unique('active_lock_key', 'z_readings_active_lock_unique');
        });
    }

    public function down(): void
    {
        Schema::table('z_readings', function (Blueprint $table) {
            $table->dropUnique('z_readings_active_lock_unique');
            $table->dropColumn('active_lock_key');
        });
    }
};
