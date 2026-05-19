<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->string('image')->nullable()->after('selling_price');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->boolean('vat_enabled')->default(true)->after('is_active');
            $table->decimal('vat_percentage', 5, 2)->default(12.00)->after('vat_enabled');
            $table->boolean('pwd_discount_enabled')->default(true)->after('vat_percentage');
            $table->boolean('senior_discount_enabled')->default(true)->after('pwd_discount_enabled');
            $table->string('contact_number')->nullable()->after('address');
            $table->string('tin_number')->nullable()->after('contact_number');
        });
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->dropColumn('image');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn([
                'vat_enabled', 'vat_percentage', 'pwd_discount_enabled',
                'senior_discount_enabled', 'contact_number', 'tin_number'
            ]);
        });
    }
};