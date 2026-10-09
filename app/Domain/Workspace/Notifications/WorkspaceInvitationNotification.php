<?php

namespace App\Domain\Workspace\Notifications;

use App\Domain\Workspace\Models\WorkspaceInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public WorkspaceInvitation $invitation)
    {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $workspace = $this->invitation->workspace;
        $inviter = $this->invitation->inviter;

        return (new MailMessage)
            ->subject(__("You've been invited to join :workspace", ['workspace' => $workspace->name]))
            ->line(__(':inviter has invited you to join the :workspace workspace as :role.', [
                'inviter' => $inviter->name ?? __('A team member'),
                'workspace' => $workspace->name,
                'role' => $this->invitation->role->label(),
            ]))
            ->action(__('View invitation'), route('invitations.show', $this->invitation->code))
            ->line(__('This invitation expires on :date.', [
                'date' => $this->invitation->expires_at?->toFormattedDayDateString(),
            ]))
            ->line(__('If you did not expect this invitation, you can ignore this email.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'workspace_id' => $this->invitation->workspace_id,
            'workspace_name' => $this->invitation->workspace->name,
            'role' => $this->invitation->role->value,
        ];
    }
}
