<?php

declare(strict_types=1);

namespace OffloadProject\InviteOnly\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use OffloadProject\InviteOnly\Exceptions\InvalidInvitationException;
use OffloadProject\InviteOnly\Exceptions\InvitationAlreadyAcceptedException;
use OffloadProject\InviteOnly\Exceptions\InvitationExpiredException;
use OffloadProject\InviteOnly\Models\Invitation;

interface InviteOnlyContract
{
    /**
     * Create a new invitation.
     *
     * @param  array{role?: string, metadata?: array<string, mixed>, expires_at?: Carbon, invited_by?: Model|int}  $options
     *
     * @throws InvalidArgumentException When email format is invalid
     */
    public function invite(string $email, ?Model $invitable = null, array $options = []): Invitation;

    /**
     * Accept an invitation by token.
     *
     * @throws InvalidInvitationException When token is invalid, cancelled, or declined
     * @throws InvitationAlreadyAcceptedException When invitation was already accepted
     * @throws InvitationExpiredException When invitation has expired
     */
    public function accept(string $token, ?Model $user = null): Invitation;

    /**
     * Decline an invitation by token.
     *
     * @throws InvalidInvitationException When token is invalid or invitation is not pending
     */
    public function decline(string $token): Invitation;

    /**
     * Cancel an invitation.
     *
     * @throws InvalidInvitationException When invitation is not pending or not found
     */
    public function cancel(Invitation|int $invitation, bool $notify = false): Invitation;

    /**
     * Resend an invitation notification.
     *
     * @throws InvalidInvitationException When invitation is not valid
     */
    public function resend(Invitation|int $invitation): Invitation;

    /**
     * Find an invitation by token.
     */
    public function find(string $token): ?Invitation;

    /**
     * Find an invitation by email, optionally scoped to an invitable.
     */
    public function findByEmail(string $email, ?Model $invitable = null): ?Invitation;

    /**
     * Get pending invitations.
     *
     * @return Collection<int, Invitation>
     */
    public function pending(?Model $invitable = null): Collection;

    /**
     * Get accepted invitations.
     *
     * @return Collection<int, Invitation>
     */
    public function accepted(?Model $invitable = null): Collection;

    /**
     * Get declined invitations.
     *
     * @return Collection<int, Invitation>
     */
    public function declined(?Model $invitable = null): Collection;

    /**
     * Get expired invitations.
     *
     * @return Collection<int, Invitation>
     */
    public function expired(?Model $invitable = null): Collection;

    /**
     * Get cancelled invitations.
     *
     * @return Collection<int, Invitation>
     */
    public function cancelled(?Model $invitable = null): Collection;

    /**
     * Mark all past-expiration pending invitations as expired.
     *
     * @return int Number of invitations marked as expired
     */
    public function markExpiredInvitations(): int;

    /**
     * Send reminders for pending invitations.
     *
     * @return int Number of reminders sent
     */
    public function sendReminders(): int;
}
