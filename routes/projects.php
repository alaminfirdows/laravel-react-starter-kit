<?php

use App\Domain\Knowledge\Http\Controllers\DecisionController;
use App\Domain\Knowledge\Http\Controllers\KnowledgeController;
use App\Domain\Project\Enums\ProjectSetupStep;
use App\Domain\Project\Http\Controllers\ProjectController;
use App\Domain\Project\Http\Controllers\ProjectLogoController;
use App\Domain\Project\Http\Controllers\ProjectSetupController;
use App\Domain\Task\Http\Controllers\ApprovalDecisionController;
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

    Route::get('{project}/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::post('{project}/tasks/{task}/completion', [TaskCompletionController::class, 'store'])->name('tasks.completion.store');
    Route::delete('{project}/tasks/{task}/completion', [TaskCompletionController::class, 'destroy'])->name('tasks.completion.destroy');
    Route::get('{project}/knowledge', [KnowledgeController::class, 'index'])->name('knowledge.index');
    Route::get('{project}/knowledge/create', [KnowledgeController::class, 'create'])->name('knowledge.create');
    Route::post('{project}/knowledge', [KnowledgeController::class, 'store'])->name('knowledge.store');
    Route::get('{project}/knowledge/{knowledgeDocument}', [KnowledgeController::class, 'show'])->name('knowledge.show');
    Route::get('{project}/knowledge/{knowledgeDocument}/edit', [KnowledgeController::class, 'edit'])->name('knowledge.edit');
    Route::put('{project}/knowledge/{knowledgeDocument}', [KnowledgeController::class, 'update'])->name('knowledge.update');

    Route::get('{project}/decisions', [DecisionController::class, 'index'])->name('decisions.index');
    Route::post('{project}/decisions', [DecisionController::class, 'store'])->name('decisions.store');

    Route::post('{project}/approvals/{approval}/decision', [ApprovalDecisionController::class, 'store'])->name('approvals.decision.store');
});
