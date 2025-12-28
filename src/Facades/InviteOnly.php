<?php

declare(strict_types=1);

namespace OffloadProject\InviteOnly\Facades;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use OffloadProject\InviteOnly\Models\Invitation;

/**
 * @method static Invitation invite(string $email, ?Model $invitable = null, array{role?: string, metadata?: array<string, mixed>, expires_at?: \Illuminate\Support\Carbon, invited_by?: Model|int} $options = [])
 * @method static Invitation accept(string $token, ?Model $user = null)
 * @method static Invitation decline(string $token)
 * @method static Invitation cancel(Invitation|int $invitation, bool $notify = false)
 * @method static Invitation resend(Invitation|int $invitation)
 * @method static Invitation|null find(string $token)
 * @method static Invitation|null findByEmail(string $email, ?Model $invitable = null)
 * @method static Collection<int, Invitation> pending(?Model $invitable = null)
 * @method static Collection<int, Invitation> accepted(?Model $invitable = null)
 * @method static Collection<int, Invitation> declined(?Model $invitable = null)
 * @method static Collection<int, Invitation> expired(?Model $invitable = null)
 * @method static Collection<int, Invitation> cancelled(?Model $invitable = null)
 * @method static int markExpiredInvitations()
 * @method static int sendReminders()
 *
 * @see \OffloadProject\InviteOnly\InviteOnly
 */
final class InviteOnly extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'invite-only';
    }
}
