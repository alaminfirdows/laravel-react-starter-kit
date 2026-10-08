<?php

namespace App\Domain\Task\Notifications;

use App\Domain\Task\Models\ActionRun;
use App\Domain\Task\Models\Task;
use Illuminate\Support\Str;

class ActionFailed extends TaskNotification
{
    public function __construct(public ActionRun $run)
    {
        parent::__construct();
    }

    protected function task(): Task
    {
        return $this->run->action->task;
    }

    protected function title(): string
    {
        return __('Run failed: :action', ['action' => $this->run->action->title]);
    }

    protected function body(): string
    {
        return Str::limit($this->run->error ?? __('The run failed.'), 300);
    }
}
