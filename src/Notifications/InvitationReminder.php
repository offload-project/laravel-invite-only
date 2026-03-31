<?php

declare(strict_types=1);

namespace OffloadProject\InviteOnly\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use OffloadProject\InviteOnly\Models\Invitation;

class InvitationReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Invitation $invitation
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invitableName = $this->getInvitableName();
        $expiresAt = $this->invitation->expires_at;

        $message = (new MailMessage)
            ->subject(__('invite-only::notifications.reminder.subject'))
            ->greeting(__('invite-only::notifications.reminder.greeting'));

        if ($invitableName !== null) {
            $message->line(__('invite-only::notifications.reminder.line_with_name', ['name' => $invitableName]));
        } else {
            $message->line(__('invite-only::notifications.reminder.line_without_name'));
        }

        if ($expiresAt !== null) {
            $message->line(__('invite-only::notifications.reminder.expires_line', ['date' => $expiresAt->format('F j, Y')]));
        }

        return $message
            ->action(__('invite-only::notifications.reminder.action_text'), $this->invitation->getAcceptUrl())
            ->line(__('invite-only::notifications.reminder.footer'));
    }

    /**
     * @return array{invitation_id: int, email: string, reminder_count: int}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'email' => $this->invitation->email,
            'reminder_count' => $this->invitation->reminder_count,
        ];
    }

    protected function getInvitableName(): ?string
    {
        $invitable = $this->invitation->invitable;

        if ($invitable === null) {
            return null;
        }

        if (method_exists($invitable, 'getInvitationName')) {
            return $invitable->getInvitationName();
        }

        if (isset($invitable->name)) {
            return $invitable->name;
        }

        return null;
    }
}
