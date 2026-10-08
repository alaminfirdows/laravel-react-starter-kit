<?php

namespace App\Domain\Task\Notifications;

use App\Domain\Task\Models\Approval;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskAction;
use Illuminate\Support\Str;

class ApprovalRequested extends TaskNotification
{
    public function __construct(public Approval $approval, public TaskAction $taskAction)
    {
        parent::__construct();
    }

    protected function task(): Task
    {
        return $this->taskAction->task;
    }

    protected function title(): string
    {
        return __('Approval requested: :action', ['action' => $this->taskAction->title]);
    }

    protected function body(): string
    {
        return Str::limit(strip_tags($this->approval->summary_md), 300);
    }
}
