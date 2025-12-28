<?php

declare(strict_types=1);

namespace OffloadProject\InviteOnly\Console;

use Illuminate\Console\Command;
use OffloadProject\InviteOnly\Facades\InviteOnly;

final class SendInvitationRemindersCommand extends Command
{
    protected $signature = 'invite-only:send-reminders
                            {--mark-expired : Also mark expired invitations}';

    protected $description = 'Send reminder emails for pending invitations';

    public function handle(): int
    {
        if (! config('invite-only.reminders.enabled', true)) {
            $this->components->warn('Invitation reminders are disabled in configuration.');

            return self::SUCCESS;
        }

        $this->components->info('Sending invitation reminders...');

        $remindersSent = InviteOnly::sendReminders();

        if ($remindersSent > 0) {
            $this->components->info("Sent {$remindersSent} reminder(s).");
        } else {
            $this->components->info('No reminders needed to be sent.');
        }

        if ($this->option('mark-expired')) {
            $this->markExpiredInvitations();
        }

        return self::SUCCESS;
    }

    private function markExpiredInvitations(): void
    {
        $this->components->info('Marking expired invitations...');

        $expiredCount = InviteOnly::markExpiredInvitations();

        if ($expiredCount > 0) {
            $this->components->info("Marked {$expiredCount} invitation(s) as expired.");
        } else {
            $this->components->info('No invitations to mark as expired.');
        }
    }
}
