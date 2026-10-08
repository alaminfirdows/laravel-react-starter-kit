<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type', 16)->default('team');
            $table->string('status', 16)->default('active');
            $table->foreignUlid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('plan')->nullable();
            $table->json('settings')->nullable();
            $table->string('logo_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('workspace_members', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 32);
            $table->foreignUlid('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('workspace_invitations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64)->unique();
            $table->string('email');
            $table->string('role', 32);
            $table->foreignUlid('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'email']);
            $table->index('email');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignUlid('current_workspace_id')
                ->nullable()
                ->after('password')
                ->constrained('workspaces')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_workspace_id');
        });

        Schema::dropIfExists('workspace_invitations');
        Schema::dropIfExists('workspace_members');
        Schema::dropIfExists('workspaces');
    }
};
