<?php

use App\Domain\Media\Enums\MediaKind;
use App\Domain\Project\Enums\BusinessModel;
use App\Domain\Project\Enums\LegalEntityStatus;
use App\Domain\Project\Enums\ProjectPhase;
use App\Domain\Project\Enums\ProjectStatus;
use App\Domain\Project\Enums\Stage;
use App\Support\Database\EnumCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('phase', 16);
            $table->string('status', 16)->default('draft');
            $table->string('one_liner', 140)->nullable();
            $table->text('description_md')->nullable();
            $table->jsonb('description_doc')->nullable();
            $table->string('website_url')->nullable();
            $table->string('primary_domain')->nullable();
            $table->string('business_model', 16)->nullable();
            $table->string('industry')->nullable();
            $table->string('stage', 16)->nullable();
            $table->string('pricing_model')->nullable();
            $table->string('revenue_band')->nullable();
            $table->string('primary_market', 2)->nullable();
            $table->jsonb('target_markets')->nullable();
            $table->jsonb('languages')->nullable();
            $table->text('target_customer')->nullable();
            $table->text('problem_statement')->nullable();
            $table->text('solution_summary')->nullable();
            $table->string('legal_entity_status', 16)->nullable();
            $table->string('entity_type')->nullable();
            $table->string('jurisdiction')->nullable();
            $table->date('founded_on')->nullable();
            $table->unsignedInteger('team_size')->nullable();
            $table->string('timezone')->nullable();
            $table->string('currency', 3)->nullable();
            $table->jsonb('goals')->nullable();
            $table->jsonb('tech')->nullable();
            $table->text('context_snapshot_md')->nullable();
            $table->timestamp('context_built_at')->nullable();
            $table->jsonb('settings')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['workspace_id', 'slug']);
            $table->index(['workspace_id', 'status']);
        });
        EnumCheck::add('projects', 'phase', ProjectPhase::class);
        EnumCheck::add('projects', 'status', ProjectStatus::class);
        EnumCheck::add('projects', 'business_model', BusinessModel::class, nullable: true);
        EnumCheck::add('projects', 'stage', Stage::class, nullable: true);
        EnumCheck::add('projects', 'legal_entity_status', LegalEntityStatus::class, nullable: true);

        Schema::create('media', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->nullableUlidMorphs('owner');
            $table->string('disk', 32);
            $table->string('path');
            $table->string('mime', 128);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt')->nullable();
            $table->string('kind', 16);
            $table->string('checksum', 64);
            $table->timestamps();
        });
        EnumCheck::add('media', 'kind', MediaKind::class);

        Schema::create('project_brands', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->jsonb('colors')->nullable();
            $table->jsonb('fonts')->nullable();
            $table->text('voice_md')->nullable();
            $table->jsonb('tone')->nullable();
            $table->foreignUlid('logo_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->jsonb('logo_variants')->nullable();
            $table->jsonb('socials')->nullable();
            $table->timestamps();
        });

        Schema::create('project_packs', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pack_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('pack_version');
            $table->foreignUlid('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at');
            $table->unique(['project_id', 'pack_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_packs');
        Schema::dropIfExists('project_brands');
        Schema::dropIfExists('media');
        Schema::dropIfExists('projects');
    }
};
