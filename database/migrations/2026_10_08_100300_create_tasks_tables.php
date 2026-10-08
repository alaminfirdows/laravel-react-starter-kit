<?php

use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Enums\ActionType;
use App\Domain\Task\Enums\DependencyKind;
use App\Domain\Task\Enums\Executor;
use App\Domain\Task\Enums\TaskPriority;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Enums\Verification;
use App\Support\Database\EnumCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_task_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('catalog_version')->nullable();
            $table->ulid('parent_id')->nullable();
            $table->unsignedSmallInteger('depth')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('category_key')->nullable();
            $table->string('title');
            $table->string('summary', 280)->nullable();
            $table->text('body_md')->nullable();
            $table->jsonb('body_doc')->nullable();
            $table->string('status', 24)->default('todo');
            $table->string('priority', 2)->default('p2');
            $table->timestamp('due_at')->nullable();
            $table->foreignUlid('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('completion_criteria')->nullable();
            $table->jsonb('expected_outputs')->nullable();
            $table->string('verification', 24)->default('none');
            $table->unsignedSmallInteger('progress_pct')->default(0);
            $table->string('blocked_reason')->nullable();
            $table->string('skipped_reason')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('completed_by_type', 16)->nullable();
            $table->ulid('completed_by_id')->nullable();
            $table->boolean('has_catalog_update')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'parent_id', 'sort_order']);
            $table->index(['project_id', 'status']);
        });
        // Self reference after create: the primary key must exist first.
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('tasks')->cascadeOnDelete();
        });
        EnumCheck::add('tasks', 'status', TaskStatus::class);
        EnumCheck::add('tasks', 'priority', TaskPriority::class);
        EnumCheck::add('tasks', 'verification', Verification::class);
        DB::statement('ALTER TABLE tasks ADD CONSTRAINT tasks_depth_check CHECK (depth BETWEEN 0 AND 2)');
        DB::statement('ALTER TABLE tasks ADD CONSTRAINT tasks_progress_check CHECK (progress_pct BETWEEN 0 AND 100)');
        DB::statement('CREATE UNIQUE INDEX tasks_project_catalog_unique ON tasks (project_id, catalog_task_id) WHERE catalog_task_id IS NOT NULL AND deleted_at IS NULL');
        DB::statement("CREATE INDEX tasks_open_idx ON tasks (project_id) WHERE status NOT IN ('done', 'skipped')");

        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->foreignUlid('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignUlid('depends_on_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('kind', 8)->default('hard');
            $table->primary(['task_id', 'depends_on_id']);
        });
        EnumCheck::add('task_dependencies', 'kind', DependencyKind::class);

        Schema::create('task_actions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('task_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_action_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 16);
            $table->string('executor', 16);
            $table->string('title');
            $table->text('instructions_md')->nullable();
            $table->foreignId('prompt_template_id')->nullable()->constrained()->nullOnDelete();
            $table->text('prompt_override_md')->nullable();
            $table->jsonb('config')->nullable();
            $table->boolean('is_required')->default(true);
            $table->boolean('requires_approval')->default(false);
            $table->string('status', 24)->default('pending');
            $table->unsignedInteger('sort_order')->default(0);
            $table->ulid('last_run_id')->nullable();          // FK added in P1 with action_runs
            $table->timestamp('completed_at')->nullable();
            $table->string('completed_by_type', 16)->nullable();
            $table->ulid('completed_by_id')->nullable();
            $table->timestamps();

            $table->index(['task_id', 'sort_order']);
        });
        EnumCheck::add('task_actions', 'type', ActionType::class);
        EnumCheck::add('task_actions', 'executor', Executor::class);
        EnumCheck::add('task_actions', 'status', ActionStatus::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('task_actions');
        Schema::dropIfExists('task_dependencies');
        Schema::dropIfExists('tasks');
    }
};
