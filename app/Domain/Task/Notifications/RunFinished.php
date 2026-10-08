<?php

namespace App\Domain\Task\Notifications;

use App\Domain\Task\Models\ActionRun;
use App\Domain\Task\Models\Task;
use Illuminate\Support\Str;

class RunFinished extends TaskNotification
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
        return __('Run finished: :action', ['action' => $this->run->action->title]);
    }

    protected function body(): string
    {
        return Str::limit(strip_tags($this->run->output_md ?? __('The run finished.')), 300);
    }
}
