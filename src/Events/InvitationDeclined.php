<?php

declare(strict_types=1);

namespace OffloadProject\InviteOnly\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use OffloadProject\InviteOnly\Models\Invitation;

final class InvitationDeclined
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Invitation $invitation
    ) {}
}
