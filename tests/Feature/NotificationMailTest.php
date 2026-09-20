<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;
use OffloadProject\InviteOnly\Facades\InviteOnly;
use OffloadProject\InviteOnly\InviteOnlyServiceProvider;
use OffloadProject\InviteOnly\Notifications\InvitationAcceptedNotification;
use OffloadProject\InviteOnly\Notifications\InvitationCancelledNotification;
use OffloadProject\InviteOnly\Notifications\InvitationReminder;
use OffloadProject\InviteOnly\Notifications\InvitationSent;

/*
 * These four render views now, so an application can publish and restyle them
 * rather than reimplementing the notifications to change a layout.
 *
 * Rendered here rather than faked: the rest of the suite asserts that a
 * notification was sent, which passes just as happily when the view behind it
 * is missing, names an undefined variable, or was never published at all.
 */

beforeEach(function (): void {
    Notification::fake();
});

it('renders the invitation', function (): void {
    $invitation = InviteOnly::invite('ada@example.com');

    $html = (string) (new InvitationSent($invitation))->toMail($invitation)->render();

    expect($html)->toContain(__('invite-only::notifications.invitation.action_text'))
        ->and($html)->toContain(__('invite-only::notifications.invitation.footer'))
        ->and($html)->toContain($invitation->token)
        ->and($html)->not->toContain('invite-only::');
});

it('renders the reminder, with and without an expiry', function (): void {
    $expiring = InviteOnly::invite('ada@example.com', null, ['expires_at' => now()->addWeek()]);

    $html = (string) (new InvitationReminder($expiring))->toMail($expiring)->render();

    expect($html)->toContain(__('invite-only::notifications.reminder.action_text'))
        // The date only appears when there is one, which is the branch a
        // single preview would never show.
        ->and($html)->toContain($expiring->expires_at->translatedFormat(__('invite-only::notifications.reminder.date_format')))
        ->and($html)->not->toContain('invite-only::');

    $forever = InviteOnly::invite('grace@example.com', null, ['expires_at' => null]);

    expect((string) (new InvitationReminder($forever))->toMail($forever)->render())
        ->not->toContain('invite-only::');
});

it('renders the accepted notice', function (): void {
    $invitation = InviteOnly::invite('ada@example.com');

    $html = (string) (new InvitationAcceptedNotification($invitation))->toMail($invitation)->render();

    expect($html)->toContain('ada@example.com')
        ->and($html)->toContain(__('invite-only::notifications.accepted.action_text'))
        ->and($html)->not->toContain('invite-only::');
});

it('renders the cancelled notice, which has nothing to click', function (): void {
    $invitation = InviteOnly::invite('ada@example.com');

    $html = (string) (new InvitationCancelledNotification($invitation))->toMail($invitation)->render();

    expect($html)->toContain(__('invite-only::notifications.cancelled.footer'))
        ->and($html)->not->toContain('invite-only::');
});

/*
 * The point of the exercise: an application can take these views and change
 * them.
 */
it('offers the views for publishing', function (): void {
    $paths = ServiceProvider::pathsToPublish(InviteOnlyServiceProvider::class, 'invite-only-views');

    expect($paths)->not->toBeEmpty();

    foreach (['invitation', 'reminder', 'accepted', 'cancelled'] as $view) {
        expect(view()->exists("invite-only::mail.{$view}"))->toBeTrue("Missing view [{$view}]");
    }
});
