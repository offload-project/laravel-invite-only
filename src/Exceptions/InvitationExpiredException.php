<?php

declare(strict_types=1);

namespace OffloadProject\InviteOnly\Exceptions;

use OffloadProject\InviteOnly\Models\Invitation;

/**
 * Exception thrown when attempting to act on an expired invitation.
 */
final class InvitationExpiredException extends InvitationException
{
    public function __construct(Invitation $invitation)
    {
        $expiredAt = $invitation->expires_at?->toDateTimeString() ?? 'unknown';

        parent::__construct(
            message: "This invitation expired on {$expiredAt}.",
            errorCode: 'INVITATION_EXPIRED',
            resolution: 'Create a new invitation for this email address, or extend expiration in config.',
            invitation: $invitation,
        );
    }

    /**
     * Get how long ago the invitation expired.
     */
    public function getExpiredDuration(): ?string
    {
        return $this->invitation?->expires_at?->diffForHumans();
    }
}
