<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('z_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_terminal_id')->constrained()->cascadeOnDelete();
            $table->date('business_date');
            $table->unsignedInteger('sequence_number');
            $table->string('reading_number');
            $table->foreignId('generated_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('generated_at');
            $table->json('snapshot');
            $table->string('status')->default('locked'); // locked | voided
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->timestamps();

            $table->unique(['pos_terminal_id', 'reading_number']);
            $table->index(['pos_terminal_id', 'business_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('z_readings');
    }
};
