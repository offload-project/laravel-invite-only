<?php

declare(strict_types=1);

namespace OffloadProject\InviteOnly\Exceptions;

use OffloadProject\InviteOnly\Models\Invitation;

/**
 * Exception thrown when an invitation operation cannot be completed
 * due to an invalid state or missing invitation.
 */
final class InvalidInvitationException extends InvitationException
{
    public static function tokenNotFound(): self
    {
        return new self(
            message: 'Invalid invitation token.',
            errorCode: 'INVITATION_TOKEN_NOT_FOUND',
            resolution: 'Verify the token is correct and the invitation has not been deleted.',
        );
    }

    public static function notFound(): self
    {
        return new self(
            message: 'Invitation not found.',
            errorCode: 'INVITATION_NOT_FOUND',
            resolution: 'Verify the invitation ID exists in the database.',
        );
    }

    public static function alreadyCancelled(Invitation $invitation): self
    {
        return new self(
            message: 'This invitation has been cancelled.',
            errorCode: 'INVITATION_CANCELLED',
            resolution: 'Cancelled invitations cannot be accepted. Create a new invitation instead.',
            invitation: $invitation,
        );
    }

    public static function alreadyDeclined(Invitation $invitation): self
    {
        return new self(
            message: 'This invitation has been declined.',
            errorCode: 'INVITATION_DECLINED',
            resolution: 'Declined invitations cannot be accepted. Create a new invitation if needed.',
            invitation: $invitation,
        );
    }

    public static function cannotDecline(Invitation $invitation): self
    {
        return new self(
            message: 'This invitation cannot be declined.',
            errorCode: 'INVITATION_NOT_DECLINABLE',
            resolution: 'Only pending invitations can be declined. Check $invitation->isPending() first.',
            invitation: $invitation,
        );
    }

    public static function cannotCancel(Invitation $invitation): self
    {
        return new self(
            message: 'Only pending invitations can be cancelled.',
            errorCode: 'INVITATION_NOT_CANCELLABLE',
            resolution: 'Check $invitation->isPending() before calling cancel().',
            invitation: $invitation,
        );
    }

    public static function cannotResend(Invitation $invitation): self
    {
        return new self(
            message: 'This invitation cannot be resent.',
            errorCode: 'INVITATION_NOT_RESENDABLE',
            resolution: 'Only valid (pending and not expired) invitations can be resent. Check $invitation->isValid() first.',
            invitation: $invitation,
        );
    }
}
