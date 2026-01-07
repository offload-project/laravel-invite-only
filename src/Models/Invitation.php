<?php

declare(strict_types=1);

namespace OffloadProject\InviteOnly\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Notifications\Notifiable;
use OffloadProject\InviteOnly\Database\Factories\InvitationFactory;
use OffloadProject\InviteOnly\Enums\InvitationStatus;

/**
 * @property int $id
 * @property string|null $invitable_type
 * @property int|null $invitable_id
 * @property string $email
 * @property string $token
 * @property InvitationStatus $status
 * @property string|null $role
 * @property array<string, mixed>|null $metadata
 * @property int|null $invited_by
 * @property int|null $accepted_by
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $accepted_at
 * @property \Illuminate\Support\Carbon|null $declined_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property \Illuminate\Support\Carbon|null $last_sent_at
 * @property int $reminder_count
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read Model|null $invitable
 * @property-read Model|null $inviter
 * @property-read Model|null $acceptedByUser
 *
 * @method static Builder<Invitation> query()
 * @method static Builder<Invitation> pending()
 * @method static Builder<Invitation> accepted()
 * @method static Builder<Invitation> declined()
 * @method static Builder<Invitation> expired()
 * @method static Builder<Invitation> cancelled()
 * @method static Builder<Invitation> valid()
 * @method static Builder<Invitation> forEmail(string $email)
 * @method static Builder<Invitation> forInvitable(Model $invitable)
 * @method static Builder<Invitation> needsReminder(int $afterDays)
 * @method static Builder<Invitation> pastExpiration()
 *
 * @mixin Builder<Invitation>
 */
final class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
    use HasFactory;

    use Notifiable;

    /**
     * @deprecated Use InvitationStatus::Pending instead
     */
    public const STATUS_PENDING = 'pending';

    /**
     * @deprecated Use InvitationStatus::Accepted instead
     */
    public const STATUS_ACCEPTED = 'accepted';

    /**
     * @deprecated Use InvitationStatus::Declined instead
     */
    public const STATUS_DECLINED = 'declined';

    /**
     * @deprecated Use InvitationStatus::Expired instead
     */
    public const STATUS_EXPIRED = 'expired';

    /**
     * @deprecated Use InvitationStatus::Cancelled instead
     */
    public const STATUS_CANCELLED = 'cancelled';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @var array<string, string> */
    protected $casts = [
        'status' => InvitationStatus::class,
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'declined_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'last_sent_at' => 'datetime',
        'reminder_count' => 'integer',
    ];

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function getTable(): string
    {
        return config('invite-only.table', 'invitations');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function invitable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function inviter(): BelongsTo
    {
        /** @var class-string<Model> $userModel */
        $userModel = config('invite-only.user_model', 'App\\Models\\User');

        return $this->belongsTo($userModel, 'invited_by');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function acceptedByUser(): BelongsTo
    {
        /** @var class-string<Model> $userModel */
        $userModel = config('invite-only.user_model', 'App\\Models\\User');

        return $this->belongsTo($userModel, 'accepted_by');
    }

    public function isPending(): bool
    {
        return $this->status === InvitationStatus::Pending;
    }

    public function isAccepted(): bool
    {
        return $this->status === InvitationStatus::Accepted;
    }

    public function isDeclined(): bool
    {
        return $this->status === InvitationStatus::Declined;
    }

    public function isExpired(): bool
    {
        if ($this->status === InvitationStatus::Expired) {
            return true;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return true;
        }

        return false;
    }

    public function isCancelled(): bool
    {
        return $this->status === InvitationStatus::Cancelled;
    }

    public function isValid(): bool
    {
        return $this->isPending() && ! $this->isExpired();
    }

    public function markAsAccepted(?Model $user = null): self
    {
        $this->status = InvitationStatus::Accepted;
        $this->accepted_at = now();

        if ($user !== null) {
            $this->accepted_by = $user->getKey();
        }

        $this->save();

        return $this;
    }

    public function markAsDeclined(): self
    {
        $this->status = InvitationStatus::Declined;
        $this->declined_at = now();
        $this->save();

        return $this;
    }

    public function markAsCancelled(): self
    {
        $this->status = InvitationStatus::Cancelled;
        $this->cancelled_at = now();
        $this->save();

        return $this;
    }

    public function markAsExpired(): self
    {
        $this->status = InvitationStatus::Expired;
        $this->save();

        return $this;
    }

    public function markAsSent(): self
    {
        $this->last_sent_at = now();
        $this->save();

        return $this;
    }

    public function incrementReminderCount(): self
    {
        $this->reminder_count++;
        $this->last_sent_at = now();
        $this->save();

        return $this;
    }

    public function getAcceptUrl(): string
    {
        return route('invite-only.invitations.accept', ['token' => $this->token]);
    }

    public function getDeclineUrl(): string
    {
        return route('invite-only.invitations.decline', ['token' => $this->token]);
    }

    public function getViewUrl(): string
    {
        return route('invite-only.invitations.show', ['token' => $this->token]);
    }

    public function routeNotificationForMail(): string
    {
        return $this->email;
    }

    /**
     * @param  Builder<Invitation>  $query
     * @return Builder<Invitation>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', InvitationStatus::Pending);
    }

    /**
     * @param  Builder<Invitation>  $query
     * @return Builder<Invitation>
     */
    public function scopeAccepted(Builder $query): Builder
    {
        return $query->where('status', InvitationStatus::Accepted);
    }

    /**
     * @param  Builder<Invitation>  $query
     * @return Builder<Invitation>
     */
    public function scopeDeclined(Builder $query): Builder
    {
        return $query->where('status', InvitationStatus::Declined);
    }

    /**
     * @param  Builder<Invitation>  $query
     * @return Builder<Invitation>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', InvitationStatus::Expired);
    }

    /**
     * @param  Builder<Invitation>  $query
     * @return Builder<Invitation>
     */
    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', InvitationStatus::Cancelled);
    }

    /**
     * @param  Builder<Invitation>  $query
     * @return Builder<Invitation>
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->where('status', InvitationStatus::Pending)
            ->where(function (Builder $q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * @param  Builder<Invitation>  $query
     * @return Builder<Invitation>
     */
    public function scopeForEmail(Builder $query, string $email): Builder
    {
        return $query->where('email', $email);
    }

    /**
     * @param  Builder<Invitation>  $query
     * @return Builder<Invitation>
     */
    public function scopeForInvitable(Builder $query, Model $invitable): Builder
    {
        return $query->where('invitable_type', $invitable->getMorphClass())
            ->where('invitable_id', $invitable->getKey());
    }

    /**
     * @param  Builder<Invitation>  $query
     * @return Builder<Invitation>
     */
    public function scopeNeedsReminder(Builder $query, int $afterDays): Builder
    {
        $maxReminders = config('invite-only.reminders.max_reminders', 2);

        return $query->where('status', InvitationStatus::Pending)
            ->where('reminder_count', '<', $maxReminders)
            ->where('created_at', '<=', now()->subDays($afterDays))
            ->where(function (Builder $q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * @param  Builder<Invitation>  $query
     * @return Builder<Invitation>
     */
    public function scopePastExpiration(Builder $query): Builder
    {
        return $query->where('status', InvitationStatus::Pending)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    protected static function newFactory(): InvitationFactory
    {
        return InvitationFactory::new();
    }
}
