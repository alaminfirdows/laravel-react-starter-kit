<?php

use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Enums\TaskStatus;

test('done and skipped are closed', function (TaskStatus $status, bool $closed) {
    expect($status->isClosed())->toBe($closed);
})->with([
    [TaskStatus::Done, true],
    [TaskStatus::Skipped, true],
    [TaskStatus::Todo, false],
    [TaskStatus::Locked, false],
    [TaskStatus::InProgress, false],
]);

test('action done and skipped are closed', function () {
    expect(ActionStatus::Done->isClosed())->toBeTrue()
        ->and(ActionStatus::Skipped->isClosed())->toBeTrue()
        ->and(ActionStatus::Pending->isClosed())->toBeFalse();
});
