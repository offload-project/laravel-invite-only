<?php

declare(strict_types=1);

namespace OffloadProject\InviteOnly\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property string|null $invitable_type
 * @property int|null $invitable_id
 * @property string $email
 * @property string $token
 * @property string $status
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
    use Notifiable;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var list<string> */
    protected $fillable = [
        'invitable_type',
        'invitable_id',
        'email',
        'token',
        'status',
        'role',
        'metadata',
        'invited_by',
        'accepted_by',
        'expires_at',
        'accepted_at',
        'declined_at',
        'cancelled_at',
        'last_sent_at',
        'reminder_count',
    ];

    /** @var array<string, string> */
    protected $casts = [
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
        return $this->status === self::STATUS_PENDING;
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function isDeclined(): bool
    {
        return $this->status === self::STATUS_DECLINED;
    }

    public function isExpired(): bool
    {
        if ($this->status === self::STATUS_EXPIRED) {
            return true;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return true;
        }

        return false;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isValid(): bool
    {
        return $this->isPending() && ! $this->isExpired();
    }

    public function markAsAccepted(?Model $user = null): self
    {
        $this->status = self::STATUS_ACCEPTED;
        $this->accepted_at = now();

        if ($user !== null) {
            $this->accepted_by = $user->getKey();
        }

        $this->save();

        return $this;
    }

    public function markAsDeclined(): self
    {
        $this->status = self::STATUS_DECLINED;
        $this->declined_at = now();
        $this->save();

        return $this;
    }

    public function markAsCancelled(): self
    {
        $this->status = self::STATUS_CANCELLED;
        $this->cancelled_at = now();
        $this->save();

        return $this;
    }

    public function markAsExpired(): self
    {
        $this->status = self::STATUS_EXPIRED;
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
        return route('invitations.accept', ['token' => $this->token]);
    }

    public function getDeclineUrl(): string
    {
        return route('invitations.decline', ['token' => $this->token]);
    }

    public function getViewUrl(): string
    {
        return route('invitations.show', ['token' => $this->token]);
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
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * @param  Builder<Invitation>  $query
     * @return Builder<Invitation>
     */
    public function scopeAccepted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACCEPTED);
    }

    /**
     * @param  Builder<Invitation>  $query
     * @return Builder<Invitation>
     */
    public function scopeDeclined(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DECLINED);
    }

    /**
     * @param  Builder<Invitation>  $query
     * @return Builder<Invitation>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_EXPIRED);
    }

    /**
     * @param  Builder<Invitation>  $query
     * @return Builder<Invitation>
     */
    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    /**
     * @param  Builder<Invitation>  $query
     * @return Builder<Invitation>
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING)
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

        return $query->where('status', self::STATUS_PENDING)
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
        return $query->where('status', self::STATUS_PENDING)
            ->where('expires_at', '!=', null)
            ->where('expires_at', '<=', now());
    }
}
