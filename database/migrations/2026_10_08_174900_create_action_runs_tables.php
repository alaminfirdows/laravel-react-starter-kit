<?php

use App\Domain\Activity\Enums\ActorType;
use App\Domain\Task\Enums\ApprovalStatus;
use App\Domain\Task\Enums\EvidenceKind;
use App\Domain\Task\Enums\RunChannel;
use App\Domain\Task\Enums\RunStatus;
use App\Support\Database\EnumCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('action_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('task_action_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('task_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 16);
            $table->string('actor_type', 16);
            $table->ulid('actor_id')->nullable();
            $table->string('client_name')->nullable();
            $table->text('rendered_prompt')->nullable();
            $table->string('status', 16)->default('started');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->text('output_md')->nullable();
            $table->jsonb('output')->nullable();
            $table->text('error')->nullable();
            $table->jsonb('usage')->nullable();
            $table->timestamps();

            $table->index(['task_action_id', 'started_at']);
            $table->index(['project_id', 'status']);
        });
        EnumCheck::add('action_runs', 'channel', RunChannel::class);
        EnumCheck::add('action_runs', 'actor_type', ActorType::class);
        EnumCheck::add('action_runs', 'status', RunStatus::class);

        Schema::table('task_actions', function (Blueprint $table) {
            $table->foreign('last_run_id')->references('id')->on('action_runs')->nullOnDelete();
        });

        Schema::create('evidence', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('task_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('task_action_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUlid('action_run_id')->nullable()->constrained()->nullOnDelete();
            $table->string('criterion_key')->nullable();
            $table->string('kind', 16);
            $table->string('label');
            $table->text('value')->nullable();
            $table->boolean('passed')->nullable();             // check_result only
            $table->foreignUlid('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('verified_by_type', 16)->nullable();
            $table->ulid('verified_by_id')->nullable();
            $table->string('created_by_type', 16);
            $table->ulid('created_by_id')->nullable();
            $table->timestamps();

            $table->index(['task_id', 'criterion_key']);
        });
        EnumCheck::add('evidence', 'kind', EvidenceKind::class);

        Schema::create('approvals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->ulidMorphs('subject');
            $table->string('requested_by_type', 16);
            $table->ulid('requested_by_id')->nullable();
            $table->string('requested_by_client')->nullable();
            $table->text('summary_md');
            $table->jsonb('payload')->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('decided_by_type', 16)->nullable();
            $table->ulid('decided_by_id')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });
        EnumCheck::add('approvals', 'status', ApprovalStatus::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('evidence');
        Schema::table('task_actions', function (Blueprint $table) {
            $table->dropForeign(['last_run_id']);
        });
        Schema::dropIfExists('action_runs');
    }
};
