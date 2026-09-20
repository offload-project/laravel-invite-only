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
        $expiresAt = $this->invitation->expires_at;

        return (new MailMessage)
            ->subject(__('invite-only::notifications.reminder.subject'))
            ->markdown('invite-only::mail.reminder', [
                'invitableName' => $this->getInvitableName(),
                // Formatted here so the view has a string to print and does
                // not need to know the date format key.
                'expiresAt' => $expiresAt?->translatedFormat(__('invite-only::notifications.reminder.date_format')),
                'url' => $this->invitation->getAcceptUrl(),
                'invitation' => $this->invitation,
            ]);
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
