<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Structured research rows (DATA_MODEL §D). Each row is mirrored to a knowledge document for search.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interviews', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('knowledge_document_id')->nullable()->constrained('knowledge_documents')->nullOnDelete();
            $table->string('person');
            $table->string('company')->nullable();
            $table->string('role')->nullable();
            $table->date('interviewed_on')->nullable();
            $table->text('problem')->nullable();
            $table->text('current_solution')->nullable();
            $table->text('pain')->nullable();
            $table->text('desired_outcome')->nullable();
            $table->text('objections')->nullable();
            $table->text('quotes')->nullable();
            $table->text('feature_requests')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'interviewed_on']);
        });

        Schema::create('competitors', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('knowledge_document_id')->nullable()->constrained('knowledge_documents')->nullOnDelete();
            $table->string('name');
            $table->string('url', 2048)->nullable();
            $table->text('pricing')->nullable();
            $table->text('icp')->nullable();
            $table->text('positioning')->nullable();
            $table->text('features')->nullable();
            $table->text('integrations')->nullable();
            $table->text('strengths')->nullable();
            $table->text('complaints')->nullable();
            $table->date('last_reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitors');
        Schema::dropIfExists('interviews');
    }
};
