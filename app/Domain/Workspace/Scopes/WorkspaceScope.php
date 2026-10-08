<?php

namespace App\Domain\Workspace\Scopes;

use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Exceptions\WorkspaceNotSetException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Fail closed: querying a tenant model without a workspace context throws.
 * Use withoutWorkspaceScope() for deliberate cross-workspace queries.
 *
 * @template TModel of Model
 *
 * @implements Scope<TModel>
 */
class WorkspaceScope implements Scope
{
    /**
     * @param  Builder<covariant TModel>  $builder
     * @param  TModel  $model
     *
     * @throws WorkspaceNotSetException
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where(
            $model->qualifyColumn('workspace_id'),
            app(WorkspaceDiscoveryService::class)->workspaceId(),
        );
    }
}
