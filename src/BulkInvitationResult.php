<?php

declare(strict_types=1);

namespace OffloadProject\InviteOnly;

use Countable;
use Illuminate\Support\Collection;
use OffloadProject\InviteOnly\Models\Invitation;

/**
 * Result object for bulk invitation operations.
 *
 * @property-read Collection<int, Invitation> $successful
 * @property-read Collection<int, array{email: string, reason: string}> $failed
 */
final class BulkInvitationResult implements Countable
{
    /**
     * @param  Collection<int, Invitation>  $successful
     * @param  Collection<int, array{email: string, reason: string}>  $failed
     */
    public function __construct(
        public readonly Collection $successful,
        public readonly Collection $failed,
    ) {}

    /**
     * Get the count of successful invitations.
     */
    public function count(): int
    {
        return $this->successful->count();
    }

    /**
     * Check if all invitations were successful.
     */
    public function allSuccessful(): bool
    {
        return $this->failed->isEmpty();
    }

    /**
     * Check if any invitations failed.
     */
    public function hasFailures(): bool
    {
        return $this->failed->isNotEmpty();
    }

    /**
     * Check if any invitations succeeded.
     */
    public function hasSuccesses(): bool
    {
        return $this->successful->isNotEmpty();
    }

    /**
     * Get the total number of emails processed.
     */
    public function total(): int
    {
        return $this->successful->count() + $this->failed->count();
    }

    /**
     * Get failed emails as a simple array.
     *
     * @return array<int, string>
     */
    public function failedEmails(): array
    {
        return $this->failed->pluck('email')->all();
    }

    /**
     * Get successful emails as a simple array.
     *
     * @return array<int, string>
     */
    public function successfulEmails(): array
    {
        return $this->successful->pluck('email')->all();
    }
}
