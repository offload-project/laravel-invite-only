<?php

declare(strict_types=1);

namespace OffloadProject\InviteOnly\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use OffloadProject\InviteOnly\Models\Invitation;

class InvitationSent extends Notification implements ShouldQueue
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

        $message = (new MailMessage)
            ->subject(__('invite-only::notifications.invitation.subject'))
            ->greeting(__('invite-only::notifications.invitation.greeting'));

        if ($invitableName !== null) {
            $message->line(__('invite-only::notifications.invitation.line_with_name', ['name' => $invitableName]));
        } else {
            $message->line(__('invite-only::notifications.invitation.line_without_name'));
        }

        return $message
            ->line(__('invite-only::notifications.invitation.action_line'))
            ->action(__('invite-only::notifications.invitation.action_text'), $this->invitation->getAcceptUrl())
            ->line(__('invite-only::notifications.invitation.footer'));
    }

    /**
     * @return array{invitation_id: int, email: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'email' => $this->invitation->email,
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
