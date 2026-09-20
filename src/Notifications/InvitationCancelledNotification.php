<?php

declare(strict_types=1);

namespace OffloadProject\InviteOnly\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use OffloadProject\InviteOnly\Models\Invitation;

class InvitationCancelledNotification extends Notification implements ShouldQueue
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
        return (new MailMessage)
            ->subject(__('invite-only::notifications.cancelled.subject'))
            ->markdown('invite-only::mail.cancelled', [
                'invitableName' => $this->getInvitableName(),
                'invitation' => $this->invitation,
            ]);
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
