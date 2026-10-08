<?php

namespace App\Domain\Task\Http\Controllers;

use App\Domain\Activity\Data\Actor;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Actions\DecideApproval;
use App\Domain\Task\Exceptions\InvalidTaskTransition;
use App\Domain\Task\Http\Requests\DecideApprovalRequest;
use App\Domain\Task\Models\Approval;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ApprovalDecisionController extends Controller
{
    public function store(DecideApprovalRequest $request, Workspace $workspace, Project $project, Approval $approval, DecideApproval $decide): RedirectResponse
    {
        try {
            $decide->handle($approval, Actor::user($request->user()), $request->boolean('approve'), $request->validated('note'));
            Inertia::flash('toast', ['type' => 'success', 'message' => $request->boolean('approve') ? __('Approved.') : __('Rejected.')]);
        } catch (InvalidTaskTransition $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }
}
