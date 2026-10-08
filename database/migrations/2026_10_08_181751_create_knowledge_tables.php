<?php

use App\Domain\Activity\Enums\ActorType;
use App\Domain\Knowledge\Enums\DocSource;
use App\Domain\Knowledge\Enums\DocStatus;
use App\Domain\Knowledge\Enums\DocType;
use App\Domain\Knowledge\Models\KnowledgeChunk;
use App\Support\Database\EnumCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::ensureVectorExtensionExists();

        Schema::create('knowledge_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('task_id')->nullable()->constrained()->nullOnDelete();
            $table->string('doc_type', 32);
            $table->string('title');
            $table->text('body_md');
            $table->string('status', 16)->default('draft');
            $table->string('source', 16)->default('user');
            $table->unsignedInteger('version')->default(1);
            $table->char('checksum', 64);
            $table->timestamp('embedded_at')->nullable();
            $table->jsonb('tags')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'doc_type', 'status']);
        });
        EnumCheck::add('knowledge_documents', 'doc_type', DocType::class);
        EnumCheck::add('knowledge_documents', 'status', DocStatus::class);
        EnumCheck::add('knowledge_documents', 'source', DocSource::class);

        Schema::create('knowledge_document_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('document_id')->constrained('knowledge_documents')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->text('body_md');
            $table->char('checksum', 64);
            $table->string('created_by_type', 16);
            $table->ulid('created_by_id')->nullable();
            $table->string('change_note')->nullable();
            $table->timestamp('created_at');

            $table->unique(['document_id', 'version']);
        });
        EnumCheck::add('knowledge_document_versions', 'created_by_type', ActorType::class);

        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('document_id')->constrained('knowledge_documents')->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->string('heading_path', 500)->nullable();
            $table->text('content');
            $table->unsignedInteger('token_count');
            $table->vector('embedding', KnowledgeChunk::DIMENSIONS)->nullable()->vectorIndex();
            $table->tsvector('tsv')->storedAs("to_tsvector('english', coalesce(heading_path, '') || ' ' || content)");
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'chunk_index']);
            $table->index('project_id');
            $table->index('tsv', algorithm: 'gin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_chunks');
        Schema::dropIfExists('knowledge_document_versions');
        Schema::dropIfExists('knowledge_documents');
    }
};
