<?php

namespace App\Domain\Workspace\Console;

use App\Domain\Workspace\Enums\WorkspaceStatus;
use App\Domain\Workspace\Models\Workspace;
use Illuminate\Console\Command;

class SetWorkspaceStatus extends Command
{
    protected $signature = 'workspace:status {slug : Workspace slug} {status : active, inactive or suspended}';

    protected $description = 'Set the status of a workspace';

    public function handle(): int
    {
        $status = WorkspaceStatus::tryFrom((string) $this->argument('status'));

        if ($status === null) {
            $this->components->error('Invalid status. Use: '.implode(', ', array_column(WorkspaceStatus::cases(), 'value')).'.');

            return self::FAILURE;
        }

        $workspace = Workspace::query()->where('slug', $this->argument('slug'))->first();

        if ($workspace === null) {
            $this->components->error('Workspace not found.');

            return self::FAILURE;
        }

        if ($workspace->isPersonal()) {
            $this->components->error('The status of a personal workspace cannot be changed.');

            return self::FAILURE;
        }

        $workspace->update(['status' => $status]);

        $this->components->info("Workspace [{$workspace->slug}] is now {$status->value}.");

        return self::SUCCESS;
    }
}
