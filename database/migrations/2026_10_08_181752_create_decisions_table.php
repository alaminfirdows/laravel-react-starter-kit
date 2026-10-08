<?php

use App\Domain\Knowledge\Enums\DocSource;
use App\Support\Database\EnumCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decisions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('task_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('decision_md');
            $table->text('rationale_md')->nullable();
            $table->jsonb('alternatives')->nullable();
            $table->foreignUlid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('decided_on');
            $table->string('source', 16)->default('user');
            $table->date('revisit_on')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'decided_on']);
        });
        EnumCheck::add('decisions', 'source', DocSource::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('decisions');
    }
};
