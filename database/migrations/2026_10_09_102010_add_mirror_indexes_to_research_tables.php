<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `isResearchMirror` looks up rows by knowledge_document_id; workspace_id backs the workspace scope.
 * project_id is already covered by the (project_id, …) composite indexes.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['interviews', 'competitors'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->index('knowledge_document_id');
                $table->index('workspace_id');
            });
        }
    }

    public function down(): void
    {
        foreach (['interviews', 'competitors'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropIndex(['knowledge_document_id']);
                $table->dropIndex(['workspace_id']);
            });
        }
    }
};
