<?php

use App\Domain\Activity\Enums\ActivityChannel;
use App\Domain\Activity\Enums\ActorType;
use App\Support\Database\EnumCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            // No FK: the log outlives soft/hard-deleted projects.
            $table->ulid('project_id')->nullable();
            $table->string('actor_type', 16);
            $table->ulid('actor_id')->nullable();
            $table->string('client_name')->nullable();
            $table->string('channel', 16);
            $table->string('event', 64);
            $table->nullableUlidMorphs('subject');
            $table->jsonb('properties')->nullable();
            $table->ipAddress('ip')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['project_id', 'created_at']);
            $table->index(['workspace_id', 'created_at']);
        });

        EnumCheck::add('activity_log', 'actor_type', ActorType::class);
        EnumCheck::add('activity_log', 'channel', ActivityChannel::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
