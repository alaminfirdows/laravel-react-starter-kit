<?php

use App\Domain\Catalog\Enums\CatalogPhase;
use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Task\Enums\ActionType;
use App\Domain\Task\Enums\DependencyKind;
use App\Domain\Task\Enums\Executor;
use App\Domain\Task\Enums\TaskPriority;
use App\Support\Database\EnumCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_categories', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description_md')->nullable();
            $table->string('phase', 32);
            $table->string('icon', 64)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        EnumCheck::add('catalog_categories', 'phase', CatalogPhase::class);

        Schema::create('prompt_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('title');
            $table->text('launcher_md')->nullable();
            $table->text('full_md');
            $table->jsonb('variables')->nullable();
            $table->string('target', 16)->default('chat');
            $table->jsonb('skill_keys')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->string('content_hash', 64);
            $table->timestamps();
        });

        Schema::create('catalog_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->foreignId('category_id')->constrained('catalog_categories')->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('catalog_tasks')->cascadeOnDelete();
            $table->string('title');
            $table->string('summary', 280)->nullable();
            $table->text('body_md')->nullable();
            $table->jsonb('body_doc')->nullable();
            $table->jsonb('applicability')->nullable();
            $table->string('priority_default', 2)->default('p2');
            $table->unsignedInteger('est_minutes')->nullable();
            $table->unsignedSmallInteger('difficulty')->nullable();
            $table->boolean('is_optional')->default(false);
            $table->jsonb('completion_criteria')->nullable();
            $table->jsonb('expected_outputs')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->string('content_hash', 64);
            $table->string('status', 16)->default('published');
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['parent_id', 'sort_order']);
        });
        EnumCheck::add('catalog_tasks', 'priority_default', TaskPriority::class);
        EnumCheck::add('catalog_tasks', 'status', CatalogStatus::class);

        Schema::create('catalog_task_dependencies', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained('catalog_tasks')->cascadeOnDelete();
            $table->foreignId('depends_on_id')->constrained('catalog_tasks')->cascadeOnDelete();
            $table->string('kind', 8)->default('hard');
            $table->primary(['task_id', 'depends_on_id']);
        });
        EnumCheck::add('catalog_task_dependencies', 'kind', DependencyKind::class);

        Schema::create('catalog_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalog_task_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('title');
            $table->string('type', 16);
            $table->string('executor', 16);
            $table->text('instructions_md')->nullable();
            $table->foreignId('prompt_template_id')->nullable()->constrained()->nullOnDelete();
            $table->jsonb('config')->nullable();
            $table->boolean('is_required')->default(true);
            $table->boolean('requires_approval')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['catalog_task_id', 'key']);
        });
        EnumCheck::add('catalog_actions', 'type', ActionType::class);
        EnumCheck::add('catalog_actions', 'executor', Executor::class);

        Schema::create('packs', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description_md')->nullable();
            $table->jsonb('audience')->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->string('content_hash', 64)->nullable();
            $table->string('status', 16)->default('published');
            $table->timestamps();
        });
        EnumCheck::add('packs', 'status', CatalogStatus::class);

        Schema::create('pack_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pack_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_task_id')->constrained()->cascadeOnDelete();
            $table->boolean('include_subtree')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unique(['pack_id', 'catalog_task_id']);
        });
    }

    public function down(): void
    {
        foreach (['pack_items', 'packs', 'catalog_actions', 'catalog_task_dependencies', 'catalog_tasks', 'prompt_templates', 'catalog_categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
