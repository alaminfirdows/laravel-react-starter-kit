<?php

namespace App\Domain\Task\Notifications;

use App\Domain\Task\Models\ActionRun;
use App\Domain\Task\Models\TaskAction;
use Illuminate\Support\Str;

class ActionFailed extends TaskNotification
{
    public function __construct(public ActionRun $run)
    {
        parent::__construct();
    }

    protected function action(): TaskAction
    {
        return $this->run->action;
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
