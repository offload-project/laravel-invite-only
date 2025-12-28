<?php

declare(strict_types=1);

namespace OffloadProject\InviteOnly\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use OffloadProject\InviteOnly\Models\Invitation;

final class InvitationReminder extends Notification implements ShouldQueue
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
            ->subject('Reminder: Your Invitation is Waiting')
            ->greeting('Hello!');

        if ($invitableName !== null) {
            $message->line("Just a friendly reminder that you have a pending invitation to join {$invitableName}.");
        } else {
            $message->line('Just a friendly reminder that you have a pending invitation waiting for you.');
        }

        if ($expiresAt !== null) {
            $message->line("This invitation will expire on {$expiresAt->format('F j, Y')}.");
        }

        return $message
            ->action('Accept Invitation', $this->invitation->getAcceptUrl())
            ->line('If you are not interested, you can safely ignore this email.');
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
