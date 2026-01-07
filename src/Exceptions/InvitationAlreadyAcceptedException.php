<?php

declare(strict_types=1);

namespace OffloadProject\InviteOnly\Exceptions;

use OffloadProject\InviteOnly\Models\Invitation;

/**
 * Exception thrown when attempting to accept an already-accepted invitation.
 */
final class InvitationAlreadyAcceptedException extends InvitationException
{
    public function __construct(Invitation $invitation)
    {
        $acceptedAt = $invitation->accepted_at?->toDateTimeString() ?? 'unknown';

        parent::__construct(
            message: "This invitation was already accepted on {$acceptedAt}.",
            errorCode: 'INVITATION_ALREADY_ACCEPTED',
            resolution: 'The user already has access. Check their membership or permissions.',
            invitation: $invitation,
        );
    }

    /**
     * Get the ID of the user who accepted the invitation.
     */
    public function getAcceptedById(): ?int
    {
        return $this->invitation?->accepted_by;
    }
}
