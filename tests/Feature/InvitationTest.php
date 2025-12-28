<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use OffloadProject\InviteOnly\Events\InvitationAccepted;
use OffloadProject\InviteOnly\Events\InvitationCancelled;
use OffloadProject\InviteOnly\Events\InvitationCreated;
use OffloadProject\InviteOnly\Events\InvitationDeclined;
use OffloadProject\InviteOnly\Exceptions\InvalidInvitationException;
use OffloadProject\InviteOnly\Exceptions\InvitationAlreadyAcceptedException;
use OffloadProject\InviteOnly\Exceptions\InvitationExpiredException;
use OffloadProject\InviteOnly\Facades\InviteOnly;
use OffloadProject\InviteOnly\Models\Invitation;
use OffloadProject\InviteOnly\Notifications\InvitationAcceptedNotification;
use OffloadProject\InviteOnly\Notifications\InvitationCancelledNotification;
use OffloadProject\InviteOnly\Notifications\InvitationSent;
use OffloadProject\InviteOnly\Tests\TestTeam;
use OffloadProject\InviteOnly\Tests\TestUser;

beforeEach(function (): void {
    Notification::fake();
    Event::fake();
});

describe('creating invitations', function (): void {
    it('can create a standalone invitation', function (): void {
        $invitation = InviteOnly::invite('test@example.com');

        expect($invitation)
            ->toBeInstanceOf(Invitation::class)
            ->email->toBe('test@example.com')
            ->status->toBe(Invitation::STATUS_PENDING)
            ->token->toHaveLength(64)
            ->invitable_type->toBeNull()
            ->invitable_id->toBeNull();

        Event::assertDispatched(InvitationCreated::class);
        Notification::assertSentTo($invitation, InvitationSent::class);
    });

    it('can create an invitation for an invitable model', function (): void {
        $team = TestTeam::create(['name' => 'Test Team']);

        $invitation = InviteOnly::invite('test@example.com', $team);

        expect($invitation)
            ->invitable_type->toBe(TestTeam::class)
            ->invitable_id->toBe($team->id);
    });

    it('can create an invitation with a role', function (): void {
        $invitation = InviteOnly::invite('test@example.com', null, [
            'role' => 'admin',
        ]);

        expect($invitation->role)->toBe('admin');
    });

    it('can create an invitation with metadata', function (): void {
        $invitation = InviteOnly::invite('test@example.com', null, [
            'metadata' => ['source' => 'referral', 'campaign' => 'summer2024'],
        ]);

        expect($invitation->metadata)->toBe(['source' => 'referral', 'campaign' => 'summer2024']);
    });

    it('can create an invitation with an inviter', function (): void {
        $inviter = TestUser::create(['name' => 'Inviter', 'email' => 'inviter@example.com']);

        $invitation = InviteOnly::invite('test@example.com', null, [
            'invited_by' => $inviter,
        ]);

        expect($invitation->invited_by)->toBe($inviter->id);
    });

    it('sets expiration based on config', function (): void {
        config(['invite-only.expiration.enabled' => true]);
        config(['invite-only.expiration.days' => 7]);

        $this->freezeTime();

        $invitation = InviteOnly::invite('test@example.com');

        expect($invitation->expires_at)->not->toBeNull();
        expect($invitation->expires_at->toDateString())->toBe(now()->addDays(7)->toDateString());
    });

    it('does not set expiration when disabled', function (): void {
        config(['invite-only.expiration.enabled' => false]);

        $invitation = InviteOnly::invite('test@example.com');

        expect($invitation->expires_at)->toBeNull();
    });
});

describe('accepting invitations', function (): void {
    it('can accept a valid invitation', function (): void {
        $invitation = InviteOnly::invite('test@example.com');
        $user = TestUser::create(['name' => 'Test User', 'email' => 'test@example.com']);

        Event::fake();

        $accepted = InviteOnly::accept($invitation->token, $user);

        expect($accepted)
            ->status->toBe(Invitation::STATUS_ACCEPTED)
            ->accepted_by->toBe($user->id)
            ->accepted_at->not->toBeNull();

        Event::assertDispatched(InvitationAccepted::class);
    });

    it('throws exception for invalid token', function (): void {
        InviteOnly::accept('invalid-token');
    })->throws(InvalidInvitationException::class);

    it('throws exception for already accepted invitation', function (): void {
        $invitation = InviteOnly::invite('test@example.com');
        $invitation->markAsAccepted();

        InviteOnly::accept($invitation->token);
    })->throws(InvitationAlreadyAcceptedException::class);

    it('throws exception for expired invitation', function (): void {
        $invitation = InviteOnly::invite('test@example.com');
        $invitation->update(['expires_at' => now()->subDay()]);

        InviteOnly::accept($invitation->token);
    })->throws(InvitationExpiredException::class);

    it('throws exception for cancelled invitation', function (): void {
        $invitation = InviteOnly::invite('test@example.com');
        $invitation->markAsCancelled();

        InviteOnly::accept($invitation->token);
    })->throws(InvalidInvitationException::class);

    it('notifies inviter when invitation is accepted', function (): void {
        $inviter = TestUser::create(['name' => 'Inviter', 'email' => 'inviter@example.com']);
        $invitation = InviteOnly::invite('test@example.com', null, ['invited_by' => $inviter]);

        Notification::fake();

        InviteOnly::accept($invitation->token);

        Notification::assertSentTo($inviter, InvitationAcceptedNotification::class);
    });
});

describe('declining invitations', function (): void {
    it('can decline a pending invitation', function (): void {
        $invitation = InviteOnly::invite('test@example.com');

        Event::fake();

        $declined = InviteOnly::decline($invitation->token);

        expect($declined)
            ->status->toBe(Invitation::STATUS_DECLINED)
            ->declined_at->not->toBeNull();

        Event::assertDispatched(InvitationDeclined::class);
    });

    it('throws exception for invalid token', function (): void {
        InviteOnly::decline('invalid-token');
    })->throws(InvalidInvitationException::class);

    it('throws exception for non-pending invitation', function (): void {
        $invitation = InviteOnly::invite('test@example.com');
        $invitation->markAsAccepted();

        InviteOnly::decline($invitation->token);
    })->throws(InvalidInvitationException::class);
});

describe('cancelling invitations', function (): void {
    it('can cancel a pending invitation', function (): void {
        $invitation = InviteOnly::invite('test@example.com');

        Event::fake();

        $cancelled = InviteOnly::cancel($invitation);

        expect($cancelled)
            ->status->toBe(Invitation::STATUS_CANCELLED)
            ->cancelled_at->not->toBeNull();

        Event::assertDispatched(InvitationCancelled::class);
    });

    it('can cancel with notification', function (): void {
        $invitation = InviteOnly::invite('test@example.com');

        Notification::fake();

        InviteOnly::cancel($invitation, notify: true);

        Notification::assertSentTo($invitation, InvitationCancelledNotification::class);
    });

    it('does not notify when notify is false', function (): void {
        $invitation = InviteOnly::invite('test@example.com');

        Notification::fake();

        InviteOnly::cancel($invitation, notify: false);

        Notification::assertNotSentTo($invitation, InvitationCancelledNotification::class);
    });

    it('throws exception for non-pending invitation', function (): void {
        $invitation = InviteOnly::invite('test@example.com');
        $invitation->markAsAccepted();

        InviteOnly::cancel($invitation);
    })->throws(InvalidInvitationException::class);
});

describe('resending invitations', function (): void {
    it('can resend a valid invitation', function (): void {
        $invitation = InviteOnly::invite('test@example.com');
        $originalSentAt = $invitation->last_sent_at;

        Notification::fake();

        $this->travel(1)->hour();

        $resent = InviteOnly::resend($invitation);

        expect($resent->last_sent_at)->toBeGreaterThan($originalSentAt);
        Notification::assertSentTo($invitation, InvitationSent::class);
    });

    it('throws exception for expired invitation', function (): void {
        $invitation = InviteOnly::invite('test@example.com');
        $invitation->update(['expires_at' => now()->subDay()]);

        InviteOnly::resend($invitation);
    })->throws(InvalidInvitationException::class);
});

describe('querying invitations', function (): void {
    it('can find invitation by token', function (): void {
        $invitation = InviteOnly::invite('test@example.com');

        $found = InviteOnly::find($invitation->token);

        expect($found)->not->toBeNull();
        expect($found->id)->toBe($invitation->id);
    });

    it('can find invitation by email', function (): void {
        $invitation = InviteOnly::invite('test@example.com');

        $found = InviteOnly::findByEmail('test@example.com');

        expect($found)->not->toBeNull();
        expect($found->id)->toBe($invitation->id);
    });

    it('can get pending invitations', function (): void {
        InviteOnly::invite('pending@example.com');
        $accepted = InviteOnly::invite('accepted@example.com');
        $accepted->markAsAccepted();

        $pending = InviteOnly::pending();

        expect($pending)->toHaveCount(1);
        expect($pending->first()->email)->toBe('pending@example.com');
    });

    it('can get accepted invitations', function (): void {
        InviteOnly::invite('pending@example.com');
        $accepted = InviteOnly::invite('accepted@example.com');
        $accepted->markAsAccepted();

        $acceptedList = InviteOnly::accepted();

        expect($acceptedList)->toHaveCount(1);
        expect($acceptedList->first()->email)->toBe('accepted@example.com');
    });

    it('can filter invitations by invitable', function (): void {
        $team1 = TestTeam::create(['name' => 'Team 1']);
        $team2 = TestTeam::create(['name' => 'Team 2']);

        InviteOnly::invite('team1@example.com', $team1);
        InviteOnly::invite('team2@example.com', $team2);

        $team1Pending = InviteOnly::pending($team1);

        expect($team1Pending)->toHaveCount(1);
        expect($team1Pending->first()->email)->toBe('team1@example.com');
    });
});

describe('HasInvitations trait', function (): void {
    it('can invite through the model', function (): void {
        $team = TestTeam::create(['name' => 'Test Team']);

        $invitation = $team->invite('member@example.com');

        expect($invitation->invitable_type)->toBe(TestTeam::class);
        expect($invitation->invitable_id)->toBe($team->id);
    });

    it('can get pending invitations', function (): void {
        $team = TestTeam::create(['name' => 'Test Team']);
        $team->invite('member@example.com');

        expect($team->pendingInvitations())->toHaveCount(1);
    });

    it('can check if email has pending invitation', function (): void {
        $team = TestTeam::create(['name' => 'Test Team']);
        $team->invite('member@example.com');

        expect($team->hasInvitationFor('member@example.com'))->toBeTrue();
        expect($team->hasInvitationFor('other@example.com'))->toBeFalse();
    });

    it('can cancel invitation by email', function (): void {
        $team = TestTeam::create(['name' => 'Test Team']);
        $team->invite('member@example.com');

        $result = $team->cancelInvitation('member@example.com');

        expect($result)->toBeTrue();
        expect($team->pendingInvitations())->toHaveCount(0);
    });

    it('can get invitation stats', function (): void {
        $team = TestTeam::create(['name' => 'Test Team']);
        $team->invite('pending@example.com');
        $accepted = $team->invite('accepted@example.com');
        $accepted->markAsAccepted();

        $stats = $team->getInvitationStats();

        expect($stats['total'])->toBe(2);
        expect($stats['pending'])->toBe(1);
        expect($stats['accepted'])->toBe(1);
    });
});

describe('CanBeInvited trait', function (): void {
    it('can get received invitations', function (): void {
        $user = TestUser::create(['name' => 'Test User', 'email' => 'test@example.com']);
        InviteOnly::invite('test@example.com');

        expect($user->receivedInvitations)->toHaveCount(1);
    });

    it('can accept invitation', function (): void {
        $user = TestUser::create(['name' => 'Test User', 'email' => 'test@example.com']);
        $invitation = InviteOnly::invite('test@example.com');

        Event::fake();

        $accepted = $user->acceptInvitation($invitation->token);

        expect($accepted->status)->toBe(Invitation::STATUS_ACCEPTED);
        expect($accepted->accepted_by)->toBe($user->id);
    });

    it('can check for pending invitations', function (): void {
        $user = TestUser::create(['name' => 'Test User', 'email' => 'test@example.com']);
        InviteOnly::invite('test@example.com');

        expect($user->hasPendingInvitation())->toBeTrue();
    });

    it('can get pending invitations', function (): void {
        $user = TestUser::create(['name' => 'Test User', 'email' => 'test@example.com']);
        InviteOnly::invite('test@example.com');

        expect($user->getPendingInvitations())->toHaveCount(1);
    });
});

describe('invitation model', function (): void {
    it('correctly identifies valid invitations', function (): void {
        $valid = InviteOnly::invite('valid@example.com');
        $expired = InviteOnly::invite('expired@example.com');
        $expired->update(['expires_at' => now()->subDay()]);

        expect($valid->isValid())->toBeTrue();
        expect($expired->isValid())->toBeFalse();
    });

    it('generates accept and decline urls', function (): void {
        $invitation = InviteOnly::invite('test@example.com');

        expect($invitation->getAcceptUrl())->toContain($invitation->token);
        expect($invitation->getDeclineUrl())->toContain($invitation->token);
    });

    it('has correct query scopes', function (): void {
        $pending = InviteOnly::invite('pending@example.com');
        $accepted = InviteOnly::invite('accepted@example.com');
        $accepted->markAsAccepted();

        expect(Invitation::pending()->count())->toBe(1);
        expect(Invitation::accepted()->count())->toBe(1);
        expect(Invitation::valid()->count())->toBe(1);
    });
});
