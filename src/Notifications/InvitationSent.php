<?php

declare(strict_types=1);

namespace OffloadProject\InviteOnly\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use OffloadProject\InviteOnly\Models\Invitation;

final class InvitationSent extends Notification implements ShouldQueue
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
            ->subject("You've Been Invited!")
            ->greeting('Hello!');

        if ($invitableName !== null) {
            $message->line("You have been invited to join {$invitableName}.");
        } else {
            $message->line('You have been invited to join us.');
        }

        return $message
            ->line('Click the button below to accept your invitation.')
            ->action('Accept Invitation', $this->invitation->getAcceptUrl())
            ->line('If you did not expect this invitation, you can ignore this email.');
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
