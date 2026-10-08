<?php

namespace App\Domain\Project\Http;

use App\Domain\Project\Http\Resources\ProjectResource;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Queries\ProjectTaskTree;
use Inertia\ProvidesInertiaProperties;
use Inertia\RenderContext;

/**
 * Props every project page needs: header, sidebar tree and abilities.
 */
final readonly class ProjectPageProps implements ProvidesInertiaProperties
{
    public function __construct(public Project $project) {}

    /**
     * @return array<string, mixed>
     */
    public function toInertiaProperties(RenderContext $context): array
    {
        return [
            'project' => ProjectResource::make($this->project),
            'tree' => fn () => app(ProjectTaskTree::class)->handle($this->project),
            'can' => [
                'update' => $context->request->user()?->can('update', $this->project) ?? false,
            ],
        ];
    }
}
