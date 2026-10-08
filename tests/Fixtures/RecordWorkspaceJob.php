<?php

namespace Tests\Fixtures;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Records the workspace id seen while the job runs.
 */
class RecordWorkspaceJob implements ShouldQueue
{
    use Queueable;

    /** @var list<string|null> */
    public static array $seen = [];

    public function handle(): void
    {
        static::$seen[] = workspaceId();
    }
}
