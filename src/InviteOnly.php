<?php

declare(strict_types=1);

namespace OffloadProject\InviteOnly;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as BaseCollection;
use InvalidArgumentException;
use OffloadProject\InviteOnly\Contracts\InviteOnlyContract;
use OffloadProject\InviteOnly\Enums\InvitationStatus;
use OffloadProject\InviteOnly\Events\InvitationAccepted;
use OffloadProject\InviteOnly\Events\InvitationCancelled;
use OffloadProject\InviteOnly\Events\InvitationCreated;
use OffloadProject\InviteOnly\Events\InvitationDeclined;
use OffloadProject\InviteOnly\Events\InvitationExpired;
use OffloadProject\InviteOnly\Exceptions\InvalidInvitationException;
use OffloadProject\InviteOnly\Exceptions\InvitationAlreadyAcceptedException;
use OffloadProject\InviteOnly\Exceptions\InvitationExpiredException;
use OffloadProject\InviteOnly\Models\Invitation;

final class InviteOnly implements InviteOnlyContract
{
    /**
     * Create a new invitation.
     *
     * @param  array{role?: string, metadata?: array<string, mixed>, expires_at?: Carbon, invited_by?: Model|int}  $options
     *
     * @throws InvalidArgumentException When email format is invalid
     */
    public function invite(string $email, ?Model $invitable = null, array $options = []): Invitation
    {
        $this->validateEmail($email);

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
            'status' => InvitationStatus::Pending,
        ]);

        $invitation->markAsSent();

        event(new InvitationCreated($invitation));

        $this->sendNotification('invitation', $invitation);

        return $invitation;
    }

    /**
     * Create multiple invitations at once.
     *
     * Invalid emails and duplicates are captured in the result's failed collection
     * rather than throwing exceptions, allowing partial success.
     *
     * @param  array<int, string>  $emails
     * @param  array{role?: string, metadata?: array<string, mixed>, expires_at?: Carbon, invited_by?: Model|int, skip_duplicates?: bool}  $options
     */
    public function inviteMany(array $emails, ?Model $invitable = null, array $options = []): BulkInvitationResult
    {
        $skipDuplicates = $options['skip_duplicates'] ?? true;
        unset($options['skip_duplicates']);

        $successful = new BaseCollection;
        $failed = new BaseCollection;

        // Get existing pending invitation emails for this invitable to check duplicates
        $existingEmails = $skipDuplicates
            ? $this->getExistingPendingEmails($emails, $invitable)
            : [];

        foreach ($emails as $email) {
            // Validate email format
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $failed->push([
                    'email' => $email,
                    'reason' => 'Invalid email format',
                ]);

                continue;
            }

            // Check for duplicates
            if (in_array($email, $existingEmails, true)) {
                $failed->push([
                    'email' => $email,
                    'reason' => 'Pending invitation already exists',
                ]);

                continue;
            }

            try {
                $invitation = $this->invite($email, $invitable, $options);
                $successful->push($invitation);
            } catch (InvalidArgumentException $e) {
                $failed->push([
                    'email' => $email,
                    'reason' => $e->getMessage(),
                ]);
            }
        }

        return new BulkInvitationResult($successful, $failed);
    }

    /**
     * Accept an invitation by token.
     *
     * @throws InvalidInvitationException When token is invalid, cancelled, or declined
     * @throws InvitationAlreadyAcceptedException When invitation was already accepted
     * @throws InvitationExpiredException When invitation has expired
     */
    public function accept(string $token, ?Model $user = null): Invitation
    {
        $invitation = $this->find($token);

        if ($invitation === null) {
            throw InvalidInvitationException::tokenNotFound();
        }

        if ($invitation->isAccepted()) {
            throw new InvitationAlreadyAcceptedException($invitation);
        }

        if ($invitation->isExpired()) {
            throw new InvitationExpiredException($invitation);
        }

        if ($invitation->isCancelled()) {
            throw InvalidInvitationException::alreadyCancelled($invitation);
        }

        if ($invitation->isDeclined()) {
            throw InvalidInvitationException::alreadyDeclined($invitation);
        }

        $invitation->markAsAccepted($user);

        event(new InvitationAccepted($invitation, $user));

        $this->sendAcceptedNotificationToInviter($invitation);

        return $invitation;
    }

    /**
     * Decline an invitation by token.
     *
     * @throws InvalidInvitationException When token is invalid or invitation is not pending
     */
    public function decline(string $token): Invitation
    {
        $invitation = $this->find($token);

        if ($invitation === null) {
            throw InvalidInvitationException::tokenNotFound();
        }

        if (! $invitation->isPending()) {
            throw InvalidInvitationException::cannotDecline($invitation);
        }

        $invitation->markAsDeclined();

        event(new InvitationDeclined($invitation));

        return $invitation;
    }

    /**
     * Cancel an invitation.
     *
     * @throws InvalidInvitationException When invitation is not pending or not found
     */
    public function cancel(Invitation|int $invitation, bool $notify = false): Invitation
    {
        $invitation = $this->resolveInvitation($invitation);

        if (! $invitation->isPending()) {
            throw InvalidInvitationException::cannotCancel($invitation);
        }

        $invitation->markAsCancelled();

        event(new InvitationCancelled($invitation));

        if ($notify) {
            $this->sendNotification('cancelled', $invitation);
        }

        return $invitation;
    }

    /**
     * Resend an invitation notification.
     *
     * @throws InvalidInvitationException When invitation is not valid
     */
    public function resend(Invitation|int $invitation): Invitation
    {
        $invitation = $this->resolveInvitation($invitation);

        if (! $invitation->isValid()) {
            throw InvalidInvitationException::cannotResend($invitation);
        }

        $invitation->markAsSent();

        $this->sendNotification('invitation', $invitation);

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
        return $this->queryByStatus(InvitationStatus::Pending, $invitable);
    }

    /**
     * Get accepted invitations.
     *
     * @return Collection<int, Invitation>
     */
    public function accepted(?Model $invitable = null): Collection
    {
        return $this->queryByStatus(InvitationStatus::Accepted, $invitable);
    }

    /**
     * Get declined invitations.
     *
     * @return Collection<int, Invitation>
     */
    public function declined(?Model $invitable = null): Collection
    {
        return $this->queryByStatus(InvitationStatus::Declined, $invitable);
    }

    /**
     * Get expired invitations.
     *
     * @return Collection<int, Invitation>
     */
    public function expired(?Model $invitable = null): Collection
    {
        return $this->queryByStatus(InvitationStatus::Expired, $invitable);
    }

    /**
     * Get cancelled invitations.
     *
     * @return Collection<int, Invitation>
     */
    public function cancelled(?Model $invitable = null): Collection
    {
        return $this->queryByStatus(InvitationStatus::Cancelled, $invitable);
    }

    /**
     * Mark all past-expiration pending invitations as expired.
     *
     * @return int Number of invitations marked as expired
     */
    public function markExpiredInvitations(): int
    {
        $expiredInvitations = Invitation::pastExpiration()->get();

        if ($expiredInvitations->isEmpty()) {
            return 0;
        }

        // Batch update for efficiency
        Invitation::pastExpiration()->update(['status' => InvitationStatus::Expired]);

        // Refresh models and dispatch events for each invitation
        foreach ($expiredInvitations as $invitation) {
            $invitation->refresh();
            event(new InvitationExpired($invitation));
        }

        return $expiredInvitations->count();
    }

    /**
     * Send reminders for pending invitations.
     *
     * Reminders are sent based on configured day thresholds. If a reminder was
     * missed (e.g., scheduler didn't run), the invitation will catch up on the
     * next run but only receive one reminder per run to avoid spam.
     *
     * @return int Number of reminders sent
     */
    public function sendReminders(): int
    {
        if (! config('invite-only.reminders.enabled', true)) {
            return 0;
        }

        /** @var array<int, int> $afterDays */
        $afterDays = config('invite-only.reminders.after_days', [3, 5]);
        $sent = 0;

        // Sort days to ensure we process in order
        sort($afterDays);

        // Track processed invitations to avoid sending multiple reminders in one run
        $processedIds = [];

        foreach ($afterDays as $index => $days) {
            $query = Invitation::needsReminder($days)
                ->where('reminder_count', '<=', $index);

            if (! empty($processedIds)) {
                $query->whereNotIn('id', $processedIds);
            }

            $invitations = $query->get();

            foreach ($invitations as $invitation) {
                $this->sendNotification('reminder', $invitation);
                $invitation->incrementReminderCount();
                $processedIds[] = $invitation->id;
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Get emails that already have pending invitations.
     *
     * @param  array<int, string>  $emails
     * @return array<int, string>
     */
    private function getExistingPendingEmails(array $emails, ?Model $invitable): array
    {
        $query = Invitation::whereIn('email', $emails)
            ->where('status', InvitationStatus::Pending);

        if ($invitable !== null) {
            $query->where('invitable_type', $invitable->getMorphClass())
                ->where('invitable_id', $invitable->getKey());
        } else {
            $query->whereNull('invitable_type');
        }

        return $query->pluck('email')->all();
    }

    /**
     * @return Collection<int, Invitation>
     */
    private function queryByStatus(InvitationStatus $status, ?Model $invitable = null): Collection
    {
        $query = Invitation::where('status', $status);

        if ($invitable !== null) {
            $query->where('invitable_type', $invitable->getMorphClass())
                ->where('invitable_id', $invitable->getKey());
        }

        return $query->latest()->get();
    }

    /**
     * @throws InvalidInvitationException
     */
    private function resolveInvitation(Invitation|int $invitation): Invitation
    {
        if ($invitation instanceof Invitation) {
            return $invitation;
        }

        $resolved = Invitation::find($invitation);

        if ($resolved === null) {
            throw InvalidInvitationException::notFound();
        }

        return $resolved;
    }

    private function getDefaultExpiration(): ?Carbon
    {
        if (! config('invite-only.expiration.enabled', true)) {
            return null;
        }

        $days = (int) config('invite-only.expiration.days', 7);

        return now()->addDays($days);
    }

    /**
     * @throws InvalidArgumentException
     */
    private function validateEmail(string $email): void
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email address');
        }
    }

    /**
     * Send a notification using the configured notification class.
     */
    private function sendNotification(string $type, Invitation $invitation): void
    {
        /** @var class-string|null $notificationClass */
        $notificationClass = config("invite-only.notifications.{$type}");

        if ($notificationClass === null) {
            return;
        }

        $invitation->notify(new $notificationClass($invitation));
    }

    /**
     * Send accepted notification to the inviter.
     */
    private function sendAcceptedNotificationToInviter(Invitation $invitation): void
    {
        /** @var class-string|null $notificationClass */
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
