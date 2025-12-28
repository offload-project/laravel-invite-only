<?php

declare(strict_types=1);

namespace OffloadProject\InviteOnly\Traits;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OffloadProject\InviteOnly\Facades\InviteOnly;
use OffloadProject\InviteOnly\Models\Invitation;

/**
 * Trait for User model to access their invitations.
 *
 * @mixin Model
 *
 * @property string $email
 */
trait CanBeInvited
{
    /**
     * Get invitations received by this user's email.
     *
     * @return HasMany<Invitation, $this>
     */
    public function receivedInvitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'email', 'email');
    }

    /**
     * Get invitations that this user has accepted.
     *
     * @return HasMany<Invitation, $this>
     */
    public function acceptedInvitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'accepted_by');
    }

    /**
     * Get invitations sent by this user.
     *
     * @return HasMany<Invitation, $this>
     */
    public function sentInvitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'invited_by');
    }

    /**
     * Accept an invitation by token.
     */
    public function acceptInvitation(string $token): Invitation
    {
        /** @var Model $this */
        return InviteOnly::accept($token, $this);
    }

    /**
     * Check if this user has a pending invitation.
     */
    public function hasPendingInvitation(?Model $invitable = null): bool
    {
        $query = $this->receivedInvitations()->pending();

        if ($invitable !== null) {
            $query->where('invitable_type', $invitable->getMorphClass())
                ->where('invitable_id', $invitable->getKey());
        }

        return $query->exists();
    }

    /**
     * Get all pending invitations for this user.
     *
     * @return Collection<int, Invitation>
     */
    public function getPendingInvitations(): Collection
    {
        return $this->receivedInvitations()
            ->pending()
            ->with('invitable', 'inviter')
            ->get();
    }

    /**
     * Get a pending invitation for a specific invitable.
     */
    public function getInvitationFor(Model $invitable): ?Invitation
    {
        return $this->receivedInvitations()
            ->where('invitable_type', $invitable->getMorphClass())
            ->where('invitable_id', $invitable->getKey())
            ->pending()
            ->first();
    }

    /**
     * Decline an invitation by token.
     */
    public function declineInvitation(string $token): Invitation
    {
        return InviteOnly::decline($token);
    }
}
