<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costing_reports', function (Blueprint $table): void {
            $table->string('reason_type', 20)->default('others')->after('proposed_price');
            $table->unsignedBigInteger('reference_id')->nullable()->after('reason_type');
        });

        Schema::create('costing_report_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('costing_report_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costing_report_attachments');

        Schema::table('costing_reports', function (Blueprint $table): void {
            $table->dropColumn(['reason_type', 'reference_id']);
        });
    }
};
