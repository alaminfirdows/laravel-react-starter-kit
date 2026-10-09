<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Community packs: owned by a workspace. Official catalog packs have no
 * owner. Public community packs are listed for others only after review.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packs', function (Blueprint $table) {
            $table->foreignUlid('owner_workspace_id')->nullable()->constrained('workspaces')->cascadeOnDelete();
            $table->string('visibility')->default('public');
            $table->string('review_status')->nullable();
            $table->text('review_note')->nullable();
            $table->index(['status', 'visibility']);
        });
    }

    public function down(): void
    {
        Schema::table('packs', function (Blueprint $table) {
            $table->dropIndex(['status', 'visibility']);
            $table->dropConstrainedForeignId('owner_workspace_id');
            $table->dropColumn(['visibility', 'review_status', 'review_note']);
        });
    }
};
