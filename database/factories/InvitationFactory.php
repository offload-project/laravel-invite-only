<?php

declare(strict_types=1);

namespace OffloadProject\InviteOnly\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use OffloadProject\InviteOnly\Enums\InvitationStatus;
use OffloadProject\InviteOnly\Models\Invitation;

/**
 * @extends Factory<Invitation>
 */
final class InvitationFactory extends Factory
{
    protected $model = Invitation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => $this->faker->unique()->safeEmail(),
            'token' => Invitation::generateToken(),
            'status' => InvitationStatus::Pending,
            'role' => null,
            'metadata' => null,
            'invitable_type' => null,
            'invitable_id' => null,
            'invited_by' => null,
            'accepted_by' => null,
            'expires_at' => now()->addDays(config('invite-only.expiration.days', 7)),
            'accepted_at' => null,
            'declined_at' => null,
            'cancelled_at' => null,
            'last_sent_at' => now(),
            'reminder_count' => 0,
        ];
    }

    /**
     * Create a pending invitation.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => InvitationStatus::Pending,
            'accepted_at' => null,
            'declined_at' => null,
            'cancelled_at' => null,
        ]);
    }

    /**
     * Create an accepted invitation.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => InvitationStatus::Accepted,
            'accepted_at' => now(),
        ]);
    }

    /**
     * Create a declined invitation.
     */
    public function declined(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => InvitationStatus::Declined,
            'declined_at' => now(),
        ]);
    }

    /**
     * Create an expired invitation.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => InvitationStatus::Expired,
            'expires_at' => now()->subDay(),
        ]);
    }

    /**
     * Create a cancelled invitation.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => InvitationStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Set the invitation to never expire.
     */
    public function neverExpires(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => null,
        ]);
    }

    /**
     * Set custom expiration days.
     */
    public function expiresInDays(int $days): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->addDays($days),
        ]);
    }

    /**
     * Set the invitation role.
     */
    public function withRole(string $role): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => $role,
        ]);
    }

    /**
     * Set custom metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): static
    {
        return $this->state(fn (array $attributes): array => [
            'metadata' => $metadata,
        ]);
    }

    /**
     * Set the inviter.
     */
    public function invitedBy(int $userId): static
    {
        return $this->state(fn (array $attributes): array => [
            'invited_by' => $userId,
        ]);
    }

    /**
     * Set who accepted the invitation.
     */
    public function acceptedBy(int $userId): static
    {
        return $this->state(fn (array $attributes): array => [
            'accepted_by' => $userId,
            'status' => InvitationStatus::Accepted,
            'accepted_at' => now(),
        ]);
    }

    /**
     * Create an invitation that needs a reminder (created X days ago).
     */
    public function needsReminder(int $daysAgo = 3): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => InvitationStatus::Pending,
            'created_at' => now()->subDays($daysAgo),
            'reminder_count' => 0,
        ]);
    }
}
