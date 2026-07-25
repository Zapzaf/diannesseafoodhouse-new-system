<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->enum('type', ['company', 'sole_proprietorship'])->default('company')->after('name');
            $table->string('company_name')->nullable()->after('type');
            $table->string('business_name')->nullable()->after('company_name');
            $table->string('owner_name')->nullable()->after('business_name');
        });

        // Backfill existing records as Companies, carrying their current name
        // forward as the company name so nothing displayed changes.
        DB::statement('UPDATE suppliers SET type = ?, company_name = name WHERE company_name IS NULL', ['company']);
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropColumn(['type', 'company_name', 'business_name', 'owner_name']);
        });
    }
};
