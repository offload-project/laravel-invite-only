<?php

declare(strict_types=1);

namespace OffloadProject\InviteOnly;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use OffloadProject\InviteOnly\Events\InvitationAccepted;
use OffloadProject\InviteOnly\Events\InvitationCancelled;
use OffloadProject\InviteOnly\Events\InvitationCreated;
use OffloadProject\InviteOnly\Events\InvitationDeclined;
use OffloadProject\InviteOnly\Events\InvitationExpired;
use OffloadProject\InviteOnly\Exceptions\InvalidInvitationException;
use OffloadProject\InviteOnly\Exceptions\InvitationAlreadyAcceptedException;
use OffloadProject\InviteOnly\Exceptions\InvitationExpiredException;
use OffloadProject\InviteOnly\Models\Invitation;

final class InviteOnly
{
    /**
     * Create a new invitation.
     *
     * @param  array{role?: string, metadata?: array<string, mixed>, expires_at?: Carbon, invited_by?: Model|int}  $options
     */
    public function invite(string $email, ?Model $invitable = null, array $options = []): Invitation
    {
        $expiresAt = $options['expires_at'] ?? $this->getDefaultExpiration();
        $invitedBy = $options['invited_by'] ?? null;

        if ($invitedBy instanceof Model) {
            $invitedBy = $invitedBy->getKey();
        }

        $invitation = Invitation::create([
            'email' => $email,
            'token' => Invitation::generateToken(),
            'invitable_type' => $invitable?->getMorphClass(),
            'invitable_id' => $invitable?->getKey(),
            'role' => $options['role'] ?? null,
            'metadata' => $options['metadata'] ?? null,
            'invited_by' => $invitedBy,
            'expires_at' => $expiresAt,
            'status' => Invitation::STATUS_PENDING,
        ]);

        $invitation->markAsSent();

        event(new InvitationCreated($invitation));

        $this->sendInvitationNotification($invitation);

        return $invitation;
    }

    /**
     * Accept an invitation by token.
     */
    public function accept(string $token, ?Model $user = null): Invitation
    {
        $invitation = $this->find($token);

        if ($invitation === null) {
            throw new InvalidInvitationException('Invalid invitation token.');
        }

        if ($invitation->isAccepted()) {
            throw new InvitationAlreadyAcceptedException($invitation);
        }

        if ($invitation->isExpired()) {
            throw new InvitationExpiredException($invitation);
        }

        if ($invitation->isCancelled()) {
            throw new InvalidInvitationException('This invitation has been cancelled.');
        }

        if ($invitation->isDeclined()) {
            throw new InvalidInvitationException('This invitation has been declined.');
        }

        $invitation->markAsAccepted($user);

        event(new InvitationAccepted($invitation, $user));

        $this->sendAcceptedNotification($invitation);

        return $invitation;
    }

    /**
     * Decline an invitation by token.
     */
    public function decline(string $token): Invitation
    {
        $invitation = $this->find($token);

        if ($invitation === null) {
            throw new InvalidInvitationException('Invalid invitation token.');
        }

        if (! $invitation->isPending()) {
            throw new InvalidInvitationException('This invitation cannot be declined.');
        }

        $invitation->markAsDeclined();

        event(new InvitationDeclined($invitation));

        return $invitation;
    }

    /**
     * Cancel an invitation.
     */
    public function cancel(Invitation|int $invitation, bool $notify = false): Invitation
    {
        $invitation = $this->resolveInvitation($invitation);

        if (! $invitation->isPending()) {
            throw new InvalidInvitationException('Only pending invitations can be cancelled.');
        }

        $invitation->markAsCancelled();

        event(new InvitationCancelled($invitation));

        if ($notify) {
            $this->sendCancelledNotification($invitation);
        }

        return $invitation;
    }

    /**
     * Resend an invitation notification.
     */
    public function resend(Invitation|int $invitation): Invitation
    {
        $invitation = $this->resolveInvitation($invitation);

        if (! $invitation->isValid()) {
            throw new InvalidInvitationException('This invitation cannot be resent.');
        }

        $invitation->markAsSent();

        $this->sendInvitationNotification($invitation);

        return $invitation;
    }

    /**
     * Find an invitation by token.
     */
    public function find(string $token): ?Invitation
    {
        return Invitation::where('token', $token)->first();
    }

    /**
     * Find an invitation by email, optionally scoped to an invitable.
     */
    public function findByEmail(string $email, ?Model $invitable = null): ?Invitation
    {
        $query = Invitation::where('email', $email);

        if ($invitable !== null) {
            $query->where('invitable_type', $invitable->getMorphClass())
                ->where('invitable_id', $invitable->getKey());
        }

        return $query->latest()->first();
    }

    /**
     * Get pending invitations.
     *
     * @return Collection<int, Invitation>
     */
    public function pending(?Model $invitable = null): Collection
    {
        return $this->queryByStatus(Invitation::STATUS_PENDING, $invitable);
    }

    /**
     * Get accepted invitations.
     *
     * @return Collection<int, Invitation>
     */
    public function accepted(?Model $invitable = null): Collection
    {
        return $this->queryByStatus(Invitation::STATUS_ACCEPTED, $invitable);
    }

    /**
     * Get declined invitations.
     *
     * @return Collection<int, Invitation>
     */
    public function declined(?Model $invitable = null): Collection
    {
        return $this->queryByStatus(Invitation::STATUS_DECLINED, $invitable);
    }

    /**
     * Get expired invitations.
     *
     * @return Collection<int, Invitation>
     */
    public function expired(?Model $invitable = null): Collection
    {
        return $this->queryByStatus(Invitation::STATUS_EXPIRED, $invitable);
    }

    /**
     * Get cancelled invitations.
     *
     * @return Collection<int, Invitation>
     */
    public function cancelled(?Model $invitable = null): Collection
    {
        return $this->queryByStatus(Invitation::STATUS_CANCELLED, $invitable);
    }

    /**
     * Mark all past-expiration pending invitations as expired.
     */
    public function markExpiredInvitations(): int
    {
        $expiredInvitations = Invitation::pastExpiration()->get();

        foreach ($expiredInvitations as $invitation) {
            $invitation->markAsExpired();
            event(new InvitationExpired($invitation));
        }

        return $expiredInvitations->count();
    }

    /**
     * Send reminders for pending invitations.
     */
    public function sendReminders(): int
    {
        if (! config('invite-only.reminders.enabled', true)) {
            return 0;
        }

        $afterDays = config('invite-only.reminders.after_days', [3, 5]);
        $sent = 0;

        foreach ($afterDays as $days) {
            $invitations = Invitation::needsReminder($days)
                ->where('reminder_count', '<', $days === min($afterDays) ? 1 : count($afterDays))
                ->get();

            foreach ($invitations as $invitation) {
                if ($this->shouldSendReminder($invitation, $days)) {
                    $this->sendReminderNotification($invitation);
                    $invitation->incrementReminderCount();
                    $sent++;
                }
            }
        }

        return $sent;
    }

    /**
     * @return Collection<int, Invitation>
     */
    private function queryByStatus(string $status, ?Model $invitable = null): Collection
    {
        $query = Invitation::where('status', $status);

        if ($invitable !== null) {
            $query->where('invitable_type', $invitable->getMorphClass())
                ->where('invitable_id', $invitable->getKey());
        }

        return $query->latest()->get();
    }

    private function resolveInvitation(Invitation|int $invitation): Invitation
    {
        if ($invitation instanceof Invitation) {
            return $invitation;
        }

        $resolved = Invitation::find($invitation);

        if ($resolved === null) {
            throw new InvalidInvitationException('Invitation not found.');
        }

        return $resolved;
    }

    private function getDefaultExpiration(): ?Carbon
    {
        if (! config('invite-only.expiration.enabled', true)) {
            return null;
        }

        $days = config('invite-only.expiration.days', 7);

        return now()->addDays($days);
    }

    private function shouldSendReminder(Invitation $invitation, int $afterDays): bool
    {
        $createdDaysAgo = $invitation->created_at->diffInDays(now());

        return $createdDaysAgo >= $afterDays;
    }

    private function sendInvitationNotification(Invitation $invitation): void
    {
        $notificationClass = config('invite-only.notifications.invitation');

        if ($notificationClass === null) {
            return;
        }

        $invitation->notify(new $notificationClass($invitation));
    }

    private function sendReminderNotification(Invitation $invitation): void
    {
        $notificationClass = config('invite-only.notifications.reminder');

        if ($notificationClass === null) {
            return;
        }

        $invitation->notify(new $notificationClass($invitation));
    }

    private function sendCancelledNotification(Invitation $invitation): void
    {
        $notificationClass = config('invite-only.notifications.cancelled');

        if ($notificationClass === null) {
            return;
        }

        $invitation->notify(new $notificationClass($invitation));
    }

    private function sendAcceptedNotification(Invitation $invitation): void
    {
        $notificationClass = config('invite-only.notifications.accepted');

        if ($notificationClass === null) {
            return;
        }

        $inviter = $invitation->inviter;

        if ($inviter === null) {
            return;
        }

        if (! method_exists($inviter, 'notify')) {
            return;
        }

        $inviter->notify(new $notificationClass($invitation));
    }
}
