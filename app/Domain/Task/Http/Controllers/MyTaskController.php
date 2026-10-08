<?php

namespace App\Domain\Task\Http\Controllers;

use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Http\Resources\AssignedTaskResource;
use App\Domain\Task\Models\Task;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyTaskController extends Controller
{
    public function index(Request $request): Response
    {
        $tasks = Task::query()
            ->whereBelongsTo($request->user(), 'assignee')
            ->whereNotIn('status', [TaskStatus::Done, TaskStatus::Skipped])
            ->with('project:id,name,slug')
            ->latest('updated_at')
            ->get();

        return Inertia::render('tasks/mine', [
            'tasks' => AssignedTaskResource::collection($tasks),
        ]);
    }
}
