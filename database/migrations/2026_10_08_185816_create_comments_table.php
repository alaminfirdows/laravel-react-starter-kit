<?php

use App\Domain\Activity\Enums\ActorType;
use App\Support\Database\EnumCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->ulidMorphs('commentable');
            $table->string('author_type', 16)->default('user');
            $table->foreignUlid('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('client_name')->nullable();
            $table->text('body_md');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignUlid('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['commentable_type', 'commentable_id', 'created_at']);
        });
        EnumCheck::add('comments', 'author_type', ActorType::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
