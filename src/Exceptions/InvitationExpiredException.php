<?php

declare(strict_types=1);

namespace OffloadProject\InviteOnly\Exceptions;

use Exception;
use OffloadProject\InviteOnly\Models\Invitation;

final class InvitationExpiredException extends Exception
{
    public function __construct(
        public readonly Invitation $invitation,
        string $message = 'This invitation has expired.'
    ) {
        parent::__construct($message);
    }
}
