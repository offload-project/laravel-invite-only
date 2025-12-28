<?php

declare(strict_types=1);

namespace OffloadProject\InviteOnly\Traits;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use OffloadProject\InviteOnly\Facades\InviteOnly;
use OffloadProject\InviteOnly\Models\Invitation;

/**
 * Trait for models that can have invitations (e.g., Team, Organization, Project).
 *
 * @mixin Model
 */
trait HasInvitations
{
    /**
     * Get all invitations for this model.
     *
     * @return MorphMany<Invitation, $this>
     */
    public function invitations(): MorphMany
    {
        return $this->morphMany(Invitation::class, 'invitable');
    }

    /**
     * Create a new invitation for this model.
     *
     * @param  array{role?: string, metadata?: array<string, mixed>, expires_at?: \Illuminate\Support\Carbon, invited_by?: Model|int}  $options
     */
    public function invite(string $email, array $options = []): Invitation
    {
        /** @var Model $this */
        return InviteOnly::invite($email, $this, $options);
    }

    /**
     * Get all pending invitations for this model.
     *
     * @return Collection<int, Invitation>
     */
    public function pendingInvitations(): Collection
    {
        return $this->invitations()->pending()->get();
    }

    /**
     * Get all accepted invitations for this model.
     *
     * @return Collection<int, Invitation>
     */
    public function acceptedInvitations(): Collection
    {
        return $this->invitations()->accepted()->get();
    }

    /**
     * Get all valid (pending and not expired) invitations for this model.
     *
     * @return Collection<int, Invitation>
     */
    public function validInvitations(): Collection
    {
        return $this->invitations()->valid()->get();
    }

    /**
     * Cancel an invitation by email.
     */
    public function cancelInvitation(string $email, bool $notify = false): bool
    {
        /** @var Model $this */
        $invitation = InviteOnly::findByEmail($email, $this);

        if ($invitation === null || ! $invitation->isPending()) {
            return false;
        }

        InviteOnly::cancel($invitation, $notify);

        return true;
    }

    /**
     * Resend an invitation by email.
     */
    public function resendInvitation(string $email): bool
    {
        /** @var Model $this */
        $invitation = InviteOnly::findByEmail($email, $this);

        if ($invitation === null || ! $invitation->isValid()) {
            return false;
        }

        InviteOnly::resend($invitation);

        return true;
    }

    /**
     * Check if an email has a pending invitation.
     */
    public function hasInvitationFor(string $email): bool
    {
        return $this->invitations()
            ->where('email', $email)
            ->pending()
            ->exists();
    }

    /**
     * Get invitation statistics for this model.
     *
     * @return array{total: int, pending: int, accepted: int, declined: int, expired: int, cancelled: int}
     */
    public function getInvitationStats(): array
    {
        $invitations = $this->invitations()->get();

        return [
            'total' => $invitations->count(),
            'pending' => $invitations->where('status', Invitation::STATUS_PENDING)->count(),
            'accepted' => $invitations->where('status', Invitation::STATUS_ACCEPTED)->count(),
            'declined' => $invitations->where('status', Invitation::STATUS_DECLINED)->count(),
            'expired' => $invitations->where('status', Invitation::STATUS_EXPIRED)->count(),
            'cancelled' => $invitations->where('status', Invitation::STATUS_CANCELLED)->count(),
        ];
    }
}
