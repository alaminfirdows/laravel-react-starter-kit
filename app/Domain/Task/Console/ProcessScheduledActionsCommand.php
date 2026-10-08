<?php

namespace App\Domain\Task\Console;

use App\Domain\Task\Actions\ProcessScheduledActions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('actions:process-scheduled')]
#[Description('Complete finished wait actions and start due scheduled actions')]
class ProcessScheduledActionsCommand extends Command
{
    public function handle(ProcessScheduledActions $process): int
    {
        $counts = $process->handle();

        $this->components->info(sprintf('Completed %d waits, started %d scheduled actions.', $counts['waits'], $counts['scheduled']));

        return self::SUCCESS;
    }
}
