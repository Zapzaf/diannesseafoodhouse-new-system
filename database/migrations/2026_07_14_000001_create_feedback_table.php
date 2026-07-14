<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->date('date');
            $table->unsignedTinyInteger('food_taste_rating');
            $table->unsignedTinyInteger('overall_experience');
            $table->unsignedTinyInteger('service_satisfaction');
            $table->unsignedTinyInteger('speed_of_service');
            $table->unsignedTinyInteger('cleanliness');
            $table->unsignedTinyInteger('friendliness');
            $table->text('improvements')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
