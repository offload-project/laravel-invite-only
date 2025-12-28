<?php

declare(strict_types=1);

namespace OffloadProject\InviteOnly\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use OffloadProject\InviteOnly\Models\Invitation;

final class InvitationCancelledNotification extends Notification implements ShouldQueue
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
            ->subject('Invitation Cancelled')
            ->greeting('Hello!');

        if ($invitableName !== null) {
            $message->line("Your invitation to join {$invitableName} has been cancelled.");
        } else {
            $message->line('Your invitation has been cancelled.');
        }

        return $message
            ->line('If you believe this was a mistake, please contact the person who sent you the invitation.');
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
