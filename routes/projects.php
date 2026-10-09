<?php

use App\Domain\Activity\Http\Controllers\ProjectActivityController;
use App\Domain\Comment\Http\Controllers\CommentResolutionController;
use App\Domain\Comment\Http\Controllers\TaskCommentController;
use App\Domain\Knowledge\Http\Controllers\CompetitorController;
use App\Domain\Knowledge\Http\Controllers\DecisionController;
use App\Domain\Knowledge\Http\Controllers\InterviewController;
use App\Domain\Knowledge\Http\Controllers\KnowledgeController;
use App\Domain\Knowledge\Http\Controllers\ResearchController;
use App\Domain\Project\Enums\ProjectSetupStep;
use App\Domain\Project\Http\Controllers\ProjectController;
use App\Domain\Project\Http\Controllers\ProjectLogoController;
use App\Domain\Project\Http\Controllers\ProjectPackController;
use App\Domain\Project\Http\Controllers\ProjectSetupController;
use App\Domain\Task\Http\Controllers\ActionRunController;
use App\Domain\Task\Http\Controllers\ApprovalController;
use App\Domain\Task\Http\Controllers\ApprovalDecisionController;
use App\Domain\Task\Http\Controllers\TaskAssigneeController;
use App\Domain\Task\Http\Controllers\TaskCatalogUpgradeController;
use App\Domain\Task\Http\Controllers\TaskCompletionController;
use App\Domain\Task\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

/*
| Loaded inside the /{workspace} tenant group (routes/workspace.php).
*/

$steps = array_column(ProjectSetupStep::cases(), 'value');

Route::prefix('projects')->name('projects.')->group(function () use ($steps) {
    Route::get('/', [ProjectController::class, 'index'])->name('index');
    Route::get('create', [ProjectController::class, 'create'])->name('create');
    Route::post('/', [ProjectController::class, 'store'])->name('store');
    Route::get('{project}', [ProjectController::class, 'show'])->name('show');

    Route::get('{project}/setup/{step}', [ProjectSetupController::class, 'edit'])->whereIn('step', $steps)->name('setup.edit');
    Route::patch('{project}/setup/{step}', [ProjectSetupController::class, 'update'])->whereIn('step', $steps)->name('setup.update');
    Route::post('{project}/logo', [ProjectLogoController::class, 'store'])->name('logo.store');
    Route::post('{project}/packs', [ProjectPackController::class, 'store'])->name('packs.store');

    Route::get('{project}/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::post('{project}/tasks/{task}/completion', [TaskCompletionController::class, 'store'])->name('tasks.completion.store');
    Route::delete('{project}/tasks/{task}/completion', [TaskCompletionController::class, 'destroy'])->name('tasks.completion.destroy');
    Route::put('{project}/tasks/{task}/assignee', [TaskAssigneeController::class, 'update'])->name('tasks.assignee.update');
    Route::post('{project}/tasks/{task}/catalog-upgrade', [TaskCatalogUpgradeController::class, 'store'])->name('tasks.catalog-upgrade.store');
    Route::post('{project}/tasks/{task}/comments', [TaskCommentController::class, 'store'])->name('tasks.comments.store');
    Route::post('{project}/tasks/{task}/comments/{comment}/resolution', [CommentResolutionController::class, 'store'])->scopeBindings()->name('tasks.comments.resolution.store');
    Route::delete('{project}/tasks/{task}/comments/{comment}/resolution', [CommentResolutionController::class, 'destroy'])->scopeBindings()->name('tasks.comments.resolution.destroy');
    Route::post('{project}/tasks/{task}/actions/{action}/runs', [ActionRunController::class, 'store'])->scopeBindings()->name('tasks.actions.runs.store');
    Route::get('{project}/knowledge', [KnowledgeController::class, 'index'])->name('knowledge.index');
    Route::get('{project}/knowledge/create', [KnowledgeController::class, 'create'])->name('knowledge.create');
    Route::post('{project}/knowledge', [KnowledgeController::class, 'store'])->name('knowledge.store');
    Route::get('{project}/knowledge/{knowledgeDocument}', [KnowledgeController::class, 'show'])->name('knowledge.show');
    Route::get('{project}/knowledge/{knowledgeDocument}/edit', [KnowledgeController::class, 'edit'])->name('knowledge.edit');
    Route::put('{project}/knowledge/{knowledgeDocument}', [KnowledgeController::class, 'update'])->name('knowledge.update');

    Route::get('{project}/research', [ResearchController::class, 'index'])->name('research.index');
    Route::post('{project}/research/interviews', [InterviewController::class, 'store'])->name('research.interviews.store');
    Route::put('{project}/research/interviews/{interview}', [InterviewController::class, 'update'])->name('research.interviews.update');
    Route::delete('{project}/research/interviews/{interview}', [InterviewController::class, 'destroy'])->name('research.interviews.destroy');
    Route::post('{project}/research/competitors', [CompetitorController::class, 'store'])->name('research.competitors.store');
    Route::put('{project}/research/competitors/{competitor}', [CompetitorController::class, 'update'])->name('research.competitors.update');
    Route::delete('{project}/research/competitors/{competitor}', [CompetitorController::class, 'destroy'])->name('research.competitors.destroy');

    Route::get('{project}/decisions', [DecisionController::class, 'index'])->name('decisions.index');
    Route::post('{project}/decisions', [DecisionController::class, 'store'])->name('decisions.store');

    Route::get('{project}/activity', [ProjectActivityController::class, 'index'])->name('activity.index');

    Route::get('{project}/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::post('{project}/approvals/{approval}/decision', [ApprovalDecisionController::class, 'store'])->name('approvals.decision.store');
});
