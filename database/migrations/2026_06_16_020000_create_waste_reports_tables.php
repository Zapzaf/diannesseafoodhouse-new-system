<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waste_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->date('report_date');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'report_date']);
        });

        Schema::create('waste_report_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('waste_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 14, 2);
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['item_id', 'reason']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waste_report_items');
        Schema::dropIfExists('waste_reports');
    }
};
