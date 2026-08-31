<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets a specific user account be designated a Delivery approver without
     * granting them full admin/branch_manager access — this app's other
     * authorization is entirely role-based, but the client specifically
     * wants approval reserved for one named reviewer (e.g. Jessica).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('can_approve_deliveries')->default(false)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('can_approve_deliveries');
        });
    }
};
