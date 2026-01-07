<?php

declare(strict_types=1);

namespace OffloadProject\InviteOnly\Enums;

enum InvitationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    public function isAccepted(): bool
    {
        return $this === self::Accepted;
    }

    public function isDeclined(): bool
    {
        return $this === self::Declined;
    }

    public function isExpired(): bool
    {
        return $this === self::Expired;
    }

    public function isCancelled(): bool
    {
        return $this === self::Cancelled;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Accepted, self::Declined, self::Expired, self::Cancelled], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Accepted => 'Accepted',
            self::Declined => 'Declined',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
        };
    }
}
