<?php

namespace App\Domain\Task\Notifications;

use App\Domain\Task\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A notification about one task: same shape in the bell menu (database) and mail.
 */
abstract class TaskNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->afterCommit();
    }

    abstract protected function task(): Task;

    abstract protected function title(): string;

    abstract protected function body(): string;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->line($this->body())
            ->action(__('Open task'), $this->url());
    }

    /**
     * @return array{title: string, body: string, url: string, project: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => $this->url(),
            'project' => $this->task()->project->name,
        ];
    }

    protected function url(): string
    {
        $task = $this->task();

        return route('projects.tasks.show', [
            'workspace' => $task->project->workspace->slug,
            'project' => $task->project->slug,
            'task' => $task->id,
        ]);
    }
}
