<?php

use App\Domain\Catalog\Enums\ResourceType;
use App\Support\Database\EnumCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('title');
            $table->string('description', 200);
            $table->string('version', 32)->default('1.0.0');
            $table->string('source_path');
            $table->boolean('in_plugin')->default(true);
            $table->boolean('in_app_agents')->default(false);
            $table->string('content_hash', 64);
            $table->timestamps();
        });

        Schema::create('catalog_task_skill', function (Blueprint $table) {
            $table->foreignId('catalog_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->boolean('required')->default(true);
            $table->primary(['catalog_task_id', 'skill_id']);
        });

        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('type', 16);
            $table->string('title');
            $table->string('url', 2048)->nullable();
            $table->text('description_md')->nullable();
            $table->boolean('is_affiliate')->default(false);
            $table->jsonb('region')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();
        });
        EnumCheck::add('resources', 'type', ResourceType::class);

        Schema::create('catalog_task_resource', function (Blueprint $table) {
            $table->foreignId('catalog_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('note')->nullable();
            $table->primary(['catalog_task_id', 'resource_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_task_resource');
        Schema::dropIfExists('resources');
        Schema::dropIfExists('catalog_task_skill');
        Schema::dropIfExists('skills');
    }
};
