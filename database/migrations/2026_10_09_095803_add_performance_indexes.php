<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Postgres does not index foreign keys. Adds indexes for FK/filter columns and the cross-workspace analytics scans.
 * Unconditional: a missing column or an existing index fails loudly.
 */
return new class extends Migration
{
    /**
     * @var array<string, list<list<string>>>
     */
    private const array INDEXES = [
        'tasks' => [['workspace_id'], ['assignee_id'], ['catalog_task_id'], ['parent_id']],
        'task_dependencies' => [['depends_on_id']],
        'task_actions' => [['project_id'], ['catalog_action_id'], ['last_run_id'], ['type', 'status']],
        'action_runs' => [['task_id']],
        'evidence' => [['task_action_id'], ['action_run_id'], ['media_id']],
        'projects' => [['owner_id']],
        'workspaces' => [['owner_id']],
        'packs' => [['owner_workspace_id']],
        'media' => [['workspace_id'], ['project_id']],
        'comments' => [['workspace_id'], ['project_id'], ['author_id']],
        'decisions' => [['workspace_id'], ['task_id']],
        'knowledge_documents' => [['workspace_id'], ['task_id']],
        'activity_log' => [['event', 'created_at']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach ($indexes as $columns) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns));
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach ($indexes as $columns) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($table.'_'.implode('_', $columns).'_index'));
            }
        }
    }
};
