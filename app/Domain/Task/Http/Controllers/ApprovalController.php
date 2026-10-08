<?php

namespace App\Domain\Task\Http\Controllers;

use App\Domain\Project\Http\ProjectPageProps;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Enums\ApprovalStatus;
use App\Domain\Task\Http\Resources\ApprovalResource;
use App\Domain\Task\Models\Approval;
use App\Domain\Task\Models\TaskAction;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalController extends Controller
{
    public const int HISTORY_LIMIT = 30;

    public function index(Workspace $workspace, Project $project): Response
    {
        Gate::authorize('view', $project);

        return Inertia::render('projects/approvals/index', [
            new ProjectPageProps($project),
            'pending' => ApprovalResource::collection($this->withSubjects(
                $project->approvals()->where('status', ApprovalStatus::Pending)->oldest()->get(),
            )),
            'decided' => Inertia::defer(fn () => ApprovalResource::collection($this->withSubjects(
                $project->approvals()->whereNot('status', ApprovalStatus::Pending)->latest('decided_at')->limit(self::HISTORY_LIMIT)->get(),
            ))),
        ]);
    }

    /**
     * @param  Collection<int, Approval>  $approvals
     * @return Collection<int, Approval>
     */
    private function withSubjects(Collection $approvals): Collection
    {
        return $approvals->loadMorph('subject', [TaskAction::class => ['task:id,title']]);
    }
}
