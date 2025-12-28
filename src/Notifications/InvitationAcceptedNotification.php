<?php

declare(strict_types=1);

namespace OffloadProject\InviteOnly\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use OffloadProject\InviteOnly\Models\Invitation;

final class InvitationAcceptedNotification extends Notification implements ShouldQueue
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
        $acceptedByEmail = $this->invitation->email;

        $message = (new MailMessage)
            ->subject('Invitation Accepted!')
            ->greeting('Good news!');

        if ($invitableName !== null) {
            $message->line("{$acceptedByEmail} has accepted your invitation to join {$invitableName}.");
        } else {
            $message->line("{$acceptedByEmail} has accepted your invitation.");
        }

        return $message
            ->line('They are now part of your team.')
            ->action('View Dashboard', url('/'));
    }

    /**
     * @return array{invitation_id: int, email: string, accepted_at: string|null}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'email' => $this->invitation->email,
            'accepted_at' => $this->invitation->accepted_at?->toISOString(),
        ];
    }

    private function getInvitableName(): ?string
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
