<?php

namespace App\Domain\Task\Providers;

use App\Domain\Task\Console\ProcessScheduledActionsCommand;
use Illuminate\Support\ServiceProvider;

class TaskServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ProcessScheduledActionsCommand::class]);
        }
    }
}
