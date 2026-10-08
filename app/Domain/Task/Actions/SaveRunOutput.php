<?php

namespace App\Domain\Task\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Task\Enums\RunStatus;
use App\Domain\Task\Exceptions\InvalidActionTransition;
use App\Domain\Task\Models\ActionRun;
use App\Domain\Task\Models\TaskAction;

/**
 * Stores a draft result on the action's started run without finishing it.
 */
class SaveRunOutput
{
    public function __construct(protected ActivityRecorder $activity) {}

    /**
     * @param  array<string, mixed>|null  $output  structured result, if any
     */
    public function handle(TaskAction $action, Actor $actor, string $outputMd, ?array $output = null): ActionRun
    {
        $run = $action->lastRun;

        if ($run === null || $run->status !== RunStatus::Started) {
            throw InvalidActionTransition::noActiveRun($action);
        }

        $run->update(array_filter(['output_md' => $outputMd, 'output' => $output], fn (mixed $value): bool => $value !== null));

        $this->activity->record('run.output_saved', $action, ['run_id' => $run->id], $actor);

        return $run;
    }
}
