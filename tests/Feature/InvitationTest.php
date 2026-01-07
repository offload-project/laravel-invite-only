<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use OffloadProject\InviteOnly\BulkInvitationResult;
use OffloadProject\InviteOnly\Enums\InvitationStatus;
use OffloadProject\InviteOnly\Events\InvitationAccepted;
use OffloadProject\InviteOnly\Events\InvitationCancelled;
use OffloadProject\InviteOnly\Events\InvitationCreated;
use OffloadProject\InviteOnly\Events\InvitationDeclined;
use OffloadProject\InviteOnly\Events\InvitationExpired;
use OffloadProject\InviteOnly\Exceptions\InvalidInvitationException;
use OffloadProject\InviteOnly\Exceptions\InvitationAlreadyAcceptedException;
use OffloadProject\InviteOnly\Exceptions\InvitationExpiredException;
use OffloadProject\InviteOnly\Facades\InviteOnly;
use OffloadProject\InviteOnly\Models\Invitation;
use OffloadProject\InviteOnly\Notifications\InvitationAcceptedNotification;
use OffloadProject\InviteOnly\Notifications\InvitationCancelledNotification;
use OffloadProject\InviteOnly\Notifications\InvitationReminder;
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
            ->status->toBe(InvitationStatus::Pending)
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

    it('throws exception for invalid email format', function (): void {
        InviteOnly::invite('invalid-email');
    })->throws(InvalidArgumentException::class, 'Invalid email address');

    it('throws exception for malformed email', function (): void {
        InviteOnly::invite('test@');
    })->throws(InvalidArgumentException::class, 'Invalid email address');
});

describe('accepting invitations', function (): void {
    it('can accept a valid invitation', function (): void {
        $invitation = InviteOnly::invite('test@example.com');
        $user = TestUser::create(['name' => 'Test User', 'email' => 'test@example.com']);

        Event::fake();

        $accepted = InviteOnly::accept($invitation->token, $user);

        expect($accepted)
            ->status->toBe(InvitationStatus::Accepted)
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
            ->status->toBe(InvitationStatus::Declined)
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
            ->status->toBe(InvitationStatus::Cancelled)
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

        expect($accepted->status)->toBe(InvitationStatus::Accepted);
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

    it('uses enum for status', function (): void {
        $invitation = InviteOnly::invite('test@example.com');

        expect($invitation->status)->toBeInstanceOf(InvitationStatus::class);
        expect($invitation->status)->toBe(InvitationStatus::Pending);
    });
});

describe('invitation status enum', function (): void {
    it('has correct status values', function (): void {
        expect(InvitationStatus::Pending->value)->toBe('pending');
        expect(InvitationStatus::Accepted->value)->toBe('accepted');
        expect(InvitationStatus::Declined->value)->toBe('declined');
        expect(InvitationStatus::Expired->value)->toBe('expired');
        expect(InvitationStatus::Cancelled->value)->toBe('cancelled');
    });

    it('has helper methods', function (): void {
        expect(InvitationStatus::Pending->isPending())->toBeTrue();
        expect(InvitationStatus::Accepted->isAccepted())->toBeTrue();
        expect(InvitationStatus::Declined->isDeclined())->toBeTrue();
        expect(InvitationStatus::Expired->isExpired())->toBeTrue();
        expect(InvitationStatus::Cancelled->isCancelled())->toBeTrue();
    });

    it('identifies terminal statuses', function (): void {
        expect(InvitationStatus::Pending->isTerminal())->toBeFalse();
        expect(InvitationStatus::Accepted->isTerminal())->toBeTrue();
        expect(InvitationStatus::Declined->isTerminal())->toBeTrue();
        expect(InvitationStatus::Expired->isTerminal())->toBeTrue();
        expect(InvitationStatus::Cancelled->isTerminal())->toBeTrue();
    });
});

describe('batch expired invitations', function (): void {
    it('marks expired invitations in batch', function (): void {
        // Create some expired invitations
        $invitation1 = InviteOnly::invite('expired1@example.com');
        $invitation1->update(['expires_at' => now()->subDay()]);

        $invitation2 = InviteOnly::invite('expired2@example.com');
        $invitation2->update(['expires_at' => now()->subDay()]);

        // Create a valid invitation that should not be affected
        $validInvitation = InviteOnly::invite('valid@example.com');

        Event::fake();

        $count = InviteOnly::markExpiredInvitations();

        expect($count)->toBe(2);
        expect($invitation1->fresh()->status)->toBe(InvitationStatus::Expired);
        expect($invitation2->fresh()->status)->toBe(InvitationStatus::Expired);
        expect($validInvitation->fresh()->status)->toBe(InvitationStatus::Pending);

        Event::assertDispatchedTimes(InvitationExpired::class, 2);
    });
});

describe('sending reminders', function (): void {
    it('sends reminders for invitations that need them', function (): void {
        config(['invite-only.reminders.enabled' => true]);
        config(['invite-only.reminders.after_days' => [3, 5]]);
        config(['invite-only.reminders.max_reminders' => 2]);

        // Create an invitation that's 3+ days old
        $invitation = Invitation::factory()->needsReminder(3)->create();

        Notification::fake();

        $count = InviteOnly::sendReminders();

        expect($count)->toBe(1);
        expect($invitation->fresh()->reminder_count)->toBe(1);
        Notification::assertSentTo($invitation, InvitationReminder::class);
    });

    it('does not send reminders when disabled', function (): void {
        config(['invite-only.reminders.enabled' => false]);

        $invitation = Invitation::factory()->needsReminder(3)->create();

        Notification::fake();

        $count = InviteOnly::sendReminders();

        expect($count)->toBe(0);
        expect($invitation->fresh()->reminder_count)->toBe(0);
        Notification::assertNotSentTo($invitation, InvitationReminder::class);
    });

    it('respects max reminders limit', function (): void {
        config(['invite-only.reminders.enabled' => true]);
        config(['invite-only.reminders.after_days' => [3, 5]]);
        config(['invite-only.reminders.max_reminders' => 2]);

        // Create an invitation that already has max reminders
        $invitation = Invitation::factory()->create([
            'created_at' => now()->subDays(10),
            'reminder_count' => 2,
        ]);

        Notification::fake();

        $count = InviteOnly::sendReminders();

        expect($count)->toBe(0);
        Notification::assertNotSentTo($invitation, InvitationReminder::class);
    });

    it('catches up on missed reminders over multiple runs', function (): void {
        config(['invite-only.reminders.enabled' => true]);
        config(['invite-only.reminders.after_days' => [3, 5]]);
        config(['invite-only.reminders.max_reminders' => 2]);

        // Create an invitation that's 6 days old but has no reminders yet
        $invitation = Invitation::factory()->create([
            'created_at' => now()->subDays(6),
            'reminder_count' => 0,
        ]);

        Notification::fake();

        // First run should catch it at day 3 threshold
        $count = InviteOnly::sendReminders();
        expect($count)->toBe(1);
        expect($invitation->fresh()->reminder_count)->toBe(1);

        // Second run should catch it at day 5 threshold
        $count = InviteOnly::sendReminders();
        expect($count)->toBe(1);
        expect($invitation->fresh()->reminder_count)->toBe(2);
    });

    it('only sends one reminder per invitation per run', function (): void {
        config(['invite-only.reminders.enabled' => true]);
        config(['invite-only.reminders.after_days' => [3, 5]]);
        config(['invite-only.reminders.max_reminders' => 2]);

        // Create an invitation that qualifies for multiple reminder thresholds
        $invitation = Invitation::factory()->create([
            'created_at' => now()->subDays(10),
            'reminder_count' => 0,
        ]);

        Notification::fake();

        $count = InviteOnly::sendReminders();

        // Should only send one reminder even though it qualifies for both thresholds
        expect($count)->toBe(1);
        expect($invitation->fresh()->reminder_count)->toBe(1);
    });

    it('does not send reminders to expired invitations', function (): void {
        config(['invite-only.reminders.enabled' => true]);
        config(['invite-only.reminders.after_days' => [3, 5]]);

        $invitation = Invitation::factory()->create([
            'created_at' => now()->subDays(4),
            'expires_at' => now()->subDay(),
            'reminder_count' => 0,
        ]);

        Notification::fake();

        $count = InviteOnly::sendReminders();

        expect($count)->toBe(0);
        Notification::assertNotSentTo($invitation, InvitationReminder::class);
    });

    it('does not send reminders to non-pending invitations', function (): void {
        config(['invite-only.reminders.enabled' => true]);
        config(['invite-only.reminders.after_days' => [3, 5]]);

        $invitation = Invitation::factory()->accepted()->create([
            'created_at' => now()->subDays(4),
            'reminder_count' => 0,
        ]);

        Notification::fake();

        $count = InviteOnly::sendReminders();

        expect($count)->toBe(0);
        Notification::assertNotSentTo($invitation, InvitationReminder::class);
    });
});

describe('bulk invitations', function (): void {
    it('can invite multiple emails at once', function (): void {
        $emails = ['one@example.com', 'two@example.com', 'three@example.com'];

        $result = InviteOnly::inviteMany($emails);

        expect($result)
            ->toBeInstanceOf(BulkInvitationResult::class)
            ->count()->toBe(3)
            ->allSuccessful()->toBeTrue()
            ->hasFailures()->toBeFalse();

        expect($result->successful)->toHaveCount(3);
        expect($result->successfulEmails())->toBe($emails);
    });

    it('can invite multiple emails to a model', function (): void {
        $team = TestTeam::create(['name' => 'Test Team']);
        $emails = ['one@example.com', 'two@example.com'];

        $result = $team->inviteMany($emails, ['role' => 'member']);

        expect($result->count())->toBe(2);
        expect($result->successful->every(fn ($inv) => $inv->invitable_id === $team->id))->toBeTrue();
        expect($result->successful->every(fn ($inv) => $inv->role === 'member'))->toBeTrue();
    });

    it('captures invalid emails as failures', function (): void {
        $emails = ['valid@example.com', 'invalid-email', 'another@example.com', 'also-bad'];

        $result = InviteOnly::inviteMany($emails);

        expect($result->count())->toBe(2);
        expect($result->hasFailures())->toBeTrue();
        expect($result->failed)->toHaveCount(2);
        expect($result->failedEmails())->toBe(['invalid-email', 'also-bad']);
    });

    it('skips duplicate pending invitations by default', function (): void {
        // Create an existing invitation
        InviteOnly::invite('existing@example.com');

        $emails = ['existing@example.com', 'new@example.com'];

        $result = InviteOnly::inviteMany($emails);

        expect($result->count())->toBe(1);
        expect($result->successfulEmails())->toBe(['new@example.com']);
        expect($result->failedEmails())->toBe(['existing@example.com']);
        expect($result->failed->first()['reason'])->toBe('Pending invitation already exists');
    });

    it('can allow duplicate invitations with skip_duplicates false', function (): void {
        InviteOnly::invite('existing@example.com');

        $emails = ['existing@example.com', 'new@example.com'];

        $result = InviteOnly::inviteMany($emails, null, ['skip_duplicates' => false]);

        expect($result->count())->toBe(2);
        expect($result->allSuccessful())->toBeTrue();
    });

    it('scopes duplicate check to invitable', function (): void {
        $team1 = TestTeam::create(['name' => 'Team 1']);
        $team2 = TestTeam::create(['name' => 'Team 2']);

        // Invite to team 1
        $team1->invite('user@example.com');

        // Same email to team 2 should work
        $result = $team2->inviteMany(['user@example.com']);

        expect($result->count())->toBe(1);
        expect($result->allSuccessful())->toBeTrue();
    });

    it('fires events for each successful invitation', function (): void {
        $emails = ['one@example.com', 'two@example.com'];

        InviteOnly::inviteMany($emails);

        Event::assertDispatchedTimes(InvitationCreated::class, 2);
    });

    it('sends notifications for each successful invitation', function (): void {
        Notification::fake();

        $emails = ['one@example.com', 'two@example.com'];

        $result = InviteOnly::inviteMany($emails);

        Notification::assertSentTimes(InvitationSent::class, 2);
    });

    it('returns correct total count', function (): void {
        $emails = ['valid@example.com', 'invalid', 'another@example.com'];

        $result = InviteOnly::inviteMany($emails);

        expect($result->total())->toBe(3);
        expect($result->count())->toBe(2); // successful only
    });

    it('handles empty email array', function (): void {
        $result = InviteOnly::inviteMany([]);

        expect($result->count())->toBe(0);
        expect($result->total())->toBe(0);
        expect($result->allSuccessful())->toBeTrue();
    });
});
