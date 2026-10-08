<?php

namespace App\Domain\Workspace\Concerns;

use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Scopes\WorkspaceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For tenant models with a `workspace_id` column.
 *
 * - Queries are limited to the current workspace (fails closed).
 * - New rows get `workspace_id` from the current workspace.
 *
 * @property string $workspace_id
 * @property-read Workspace $workspace
 */
trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope(new WorkspaceScope);

        static::creating(function (Model $model): void {
            if (blank($model->getAttribute('workspace_id'))) {
                $model->setAttribute(
                    'workspace_id',
                    app(WorkspaceDiscoveryService::class)->workspaceId(),
                );
            }
        });
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Query without the workspace restriction (admin, console, cross-tenant jobs).
     *
     * @return Builder<static>
     */
    public static function withoutWorkspaceScope(): Builder
    {
        return static::query()->withoutGlobalScope(WorkspaceScope::class);
    }

    /**
     * Query one explicit workspace, independent of the current context.
     *
     * @return Builder<static>
     */
    public static function forWorkspace(Workspace|string $workspace): Builder
    {
        $id = $workspace instanceof Workspace ? $workspace->id : $workspace;

        return static::withoutWorkspaceScope()
            ->where(static::query()->qualifyColumn('workspace_id'), $id);
    }
}
