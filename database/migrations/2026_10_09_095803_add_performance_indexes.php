<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Postgres does not index foreign keys. Adds single-column indexes for FK/filter columns that have
 * no index starting with that column yet, plus the cross-workspace analytics scans.
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
                if (! Schema::hasColumns($table, $columns) || $this->isIndexed($table, $columns)) {
                    continue;
                }

                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns));
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach ($indexes as $columns) {
                $name = $table.'_'.implode('_', $columns).'_index';

                if (Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
                }
            }
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function isIndexed(string $table, array $columns): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (array_slice($index['columns'], 0, count($columns)) === $columns) {
                return true;
            }
        }

        return false;
    }
};
