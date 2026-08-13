<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Developer Updates / Changelog: system-wide announcements every user
     * can browse (What's New), managed by admins.
     */
    public function up(): void
    {
        Schema::create('changelog_updates', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            // new_feature | improvement | bug_fix | security
            $table->string('type');
            $table->string('image')->nullable();
            $table->date('released_at');
            // Lets an admin draft an update before it's visible to everyone.
            $table->boolean('is_published')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_published', 'released_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('changelog_updates');
    }
};
