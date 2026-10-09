<?php

use App\Domain\Activity\Http\Controllers\WorkspaceActivityController;
use App\Domain\Catalog\Http\Controllers\CommunityPackController;
use App\Domain\Task\Http\Controllers\MyTaskController;
use App\Domain\Workspace\Http\Controllers\InvitationController;
use App\Domain\Workspace\Http\Controllers\WorkspaceConnectionController;
use App\Domain\Workspace\Http\Controllers\WorkspaceController;
use App\Domain\Workspace\Http\Controllers\WorkspaceInvitationController;
use App\Domain\Workspace\Http\Controllers\WorkspaceMemberController;
use App\Domain\Workspace\Http\Controllers\WorkspaceSettingsController;
use App\Domain\Workspace\Http\Middleware\DiscoverWorkspace;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

/*
| Global workspace routes (no {workspace} in the URL).
*/

Route::get('invitations/{invitation}', [InvitationController::class, 'show'])->name('invitations.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [WorkspaceController::class, 'redirectToCurrent'])->name('dashboard.redirect');

    Route::get('workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::post('workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');

    Route::post('invitations/{invitation}/accept', [InvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [InvitationController::class, 'decline'])->name('invitations.decline');

    Route::post('notifications/read', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
});

/*
| Tenant routes: /{workspace}/... Register these last; {workspace} matches
| any slug, so fixed first segments must win. Reserved slugs keep them apart.
*/

Route::prefix('{workspace}')
    ->where(['workspace' => '[a-z0-9]+(?:-[a-z0-9]+)*'])
    ->middleware(['auth', 'verified', DiscoverWorkspace::class])
    ->scopeBindings()
    ->group(function () {
        Route::inertia('dashboard', 'dashboard')->name('dashboard');
        Route::get('my-tasks', [MyTaskController::class, 'index'])->name('tasks.mine');

        require __DIR__.'/projects.php';

        Route::resource('packs', CommunityPackController::class)
            ->only(['index', 'create', 'store', 'edit', 'update'])
            ->parameters(['packs' => 'pack:key']);

        Route::get('settings', fn () => to_route('workspace.settings.edit'))->name('workspace.settings');

        Route::prefix('settings')->name('workspace.')->group(function () {
            Route::get('general', [WorkspaceSettingsController::class, 'edit'])->name('settings.edit');
            Route::patch('general', [WorkspaceSettingsController::class, 'update'])->name('settings.update');
            Route::post('general/logo', [WorkspaceSettingsController::class, 'updateLogo'])->name('settings.logo.update');
            Route::delete('general/logo', [WorkspaceSettingsController::class, 'destroyLogo'])->name('settings.logo.destroy');
            Route::delete('general', [WorkspaceSettingsController::class, 'destroy'])->name('settings.destroy');

            Route::get('members', [WorkspaceMemberController::class, 'index'])->name('members.index');
            Route::delete('members/leave', [WorkspaceMemberController::class, 'leave'])->name('members.leave');
            Route::patch('members/{member}', [WorkspaceMemberController::class, 'update'])->name('members.update');
            Route::delete('members/{member}', [WorkspaceMemberController::class, 'destroy'])->name('members.destroy');
            Route::post('members/{member}/transfer', [WorkspaceMemberController::class, 'transferOwnership'])
                ->name('members.transfer');

            Route::get('activity', [WorkspaceActivityController::class, 'index'])->name('activity.index');
            Route::delete('connections/{token}', [WorkspaceConnectionController::class, 'destroy'])->name('connections.destroy');

            Route::get('invitations', [WorkspaceInvitationController::class, 'index'])->name('invitations.index');
            Route::post('invitations', [WorkspaceInvitationController::class, 'store'])
                ->middleware('throttle:workspace-invitations')
                ->name('invitations.store');
            Route::post('invitations/{invitation}/resend', [WorkspaceInvitationController::class, 'resend'])
                ->middleware('throttle:workspace-invitations')
                ->name('invitations.resend');
            Route::delete('invitations/{invitation}', [WorkspaceInvitationController::class, 'destroy'])->name('invitations.destroy');
        });
    });
