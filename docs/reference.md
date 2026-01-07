# API Reference

## Facade Methods

### `InviteOnly::invite()`

Create a new invitation.

```php
InviteOnly::invite(
    string $email,
    ?Model $invitable = null,
    array $options = []
): Invitation
```

**Options:**
- `role` (string) - Role to assign
- `metadata` (array) - Additional data
- `expires_at` (Carbon) - Custom expiration
- `invited_by` (Model|int) - Inviter user

**Throws:** `InvalidArgumentException` for invalid email

---

### `InviteOnly::accept()`

Accept an invitation by token.

```php
InviteOnly::accept(string $token, ?Model $user = null): Invitation
```

**Throws:**
- `InvalidInvitationException` - Token not found, cancelled, or declined
- `InvitationAlreadyAcceptedException` - Already accepted
- `InvitationExpiredException` - Expired

---

### `InviteOnly::decline()`

Decline an invitation by token.

```php
InviteOnly::decline(string $token): Invitation
```

**Throws:** `InvalidInvitationException`

---

### `InviteOnly::cancel()`

Cancel a pending invitation.

```php
InviteOnly::cancel(Invitation|int $invitation, bool $notify = false): Invitation
```

**Throws:** `InvalidInvitationException` if not pending

---

### `InviteOnly::resend()`

Resend invitation notification.

```php
InviteOnly::resend(Invitation|int $invitation): Invitation
```

**Throws:** `InvalidInvitationException` if not valid

---

### `InviteOnly::find()`

Find invitation by token.

```php
InviteOnly::find(string $token): ?Invitation
```

---

### `InviteOnly::findByEmail()`

Find invitation by email, optionally scoped.

```php
InviteOnly::findByEmail(string $email, ?Model $invitable = null): ?Invitation
```

---

### Query Methods

```php
InviteOnly::pending(?Model $invitable = null): Collection
InviteOnly::accepted(?Model $invitable = null): Collection
InviteOnly::declined(?Model $invitable = null): Collection
InviteOnly::expired(?Model $invitable = null): Collection
InviteOnly::cancelled(?Model $invitable = null): Collection
```

---

### `InviteOnly::markExpiredInvitations()`

Batch mark expired invitations.

```php
InviteOnly::markExpiredInvitations(): int  // Returns count
```

---

### `InviteOnly::sendReminders()`

Send reminder notifications.

```php
InviteOnly::sendReminders(): int  // Returns count sent
```

---

## Invitation Model

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | int | Primary key |
| `email` | string | Invitee email |
| `token` | string | Unique 64-char token |
| `status` | InvitationStatus | Current status |
| `role` | ?string | Assigned role |
| `metadata` | ?array | Custom data |
| `invitable_type` | ?string | Polymorphic type |
| `invitable_id` | ?int | Polymorphic ID |
| `invited_by` | ?int | Inviter user ID |
| `accepted_by` | ?int | Accepter user ID |
| `expires_at` | ?Carbon | Expiration timestamp |
| `accepted_at` | ?Carbon | Acceptance timestamp |
| `declined_at` | ?Carbon | Decline timestamp |
| `cancelled_at` | ?Carbon | Cancellation timestamp |
| `last_sent_at` | ?Carbon | Last notification sent |
| `reminder_count` | int | Reminders sent |

### Status Methods

```php
$invitation->isPending(): bool
$invitation->isAccepted(): bool
$invitation->isDeclined(): bool
$invitation->isExpired(): bool
$invitation->isCancelled(): bool
$invitation->isValid(): bool  // Pending AND not expired
```

### URL Methods

```php
$invitation->getAcceptUrl(): string
$invitation->getDeclineUrl(): string
$invitation->getViewUrl(): string
```

### Relationships

```php
$invitation->invitable    // Team, Organization, etc.
$invitation->inviter      // User who sent
$invitation->acceptedByUser  // User who accepted
```

### Query Scopes

```php
Invitation::pending()->get();
Invitation::accepted()->get();
Invitation::declined()->get();
Invitation::expired()->get();
Invitation::cancelled()->get();
Invitation::valid()->get();
Invitation::forEmail('user@example.com')->get();
Invitation::forInvitable($team)->get();
Invitation::needsReminder(3)->get();
Invitation::pastExpiration()->get();
```

---

## InvitationStatus Enum

```php
use OffloadProject\InviteOnly\Enums\InvitationStatus;

InvitationStatus::Pending    // 'pending'
InvitationStatus::Accepted   // 'accepted'
InvitationStatus::Declined   // 'declined'
InvitationStatus::Expired    // 'expired'
InvitationStatus::Cancelled  // 'cancelled'
```

### Methods

```php
$status->value: string
$status->label(): string        // "Pending", "Accepted", etc.
$status->isPending(): bool
$status->isAccepted(): bool
$status->isDeclined(): bool
$status->isExpired(): bool
$status->isCancelled(): bool
$status->isTerminal(): bool     // True for all except Pending
```

---

## HasInvitations Trait

For models that can have invitations (Team, Organization, etc.)

```php
$team->invitations(): MorphMany
$team->invite(string $email, array $options = []): Invitation
$team->pendingInvitations(): Collection
$team->acceptedInvitations(): Collection
$team->validInvitations(): Collection
$team->cancelInvitation(string $email, bool $notify = false): bool
$team->resendInvitation(string $email): bool
$team->hasInvitationFor(string $email): bool
$team->getInvitationStats(): array
```

---

## CanBeInvited Trait

For the User model.

```php
$user->receivedInvitations: Collection  // By email
$user->acceptedInvitations: Collection  // Accepted by this user
$user->sentInvitations: Collection      // Invited by this user
$user->acceptInvitation(string $token): Invitation
$user->declineInvitation(string $token): Invitation
$user->hasPendingInvitation(?Model $invitable = null): bool
$user->getPendingInvitations(): Collection
```

---

## Events

| Event | Properties |
|-------|------------|
| `InvitationCreated` | `Invitation $invitation` |
| `InvitationAccepted` | `Invitation $invitation`, `?Model $user` |
| `InvitationDeclined` | `Invitation $invitation` |
| `InvitationCancelled` | `Invitation $invitation` |
| `InvitationExpired` | `Invitation $invitation` |

---

## Exceptions

All extend `InvitationException`.

| Exception | Error Codes |
|-----------|-------------|
| `InvalidInvitationException` | `INVITATION_TOKEN_NOT_FOUND`, `INVITATION_NOT_FOUND`, `INVITATION_CANCELLED`, `INVITATION_DECLINED`, `INVITATION_NOT_DECLINABLE`, `INVITATION_NOT_CANCELLABLE`, `INVITATION_NOT_RESENDABLE` |
| `InvitationExpiredException` | `INVITATION_EXPIRED` |
| `InvitationAlreadyAcceptedException` | `INVITATION_ALREADY_ACCEPTED` |

### Exception Properties

```php
$e->getMessage(): string
$e->errorCode: string
$e->resolution: string
$e->invitation: ?Invitation
$e->toArray(): array
$e->getDocumentationUrl(): string
```

---

## Configuration

```php
// config/invite-only.php
return [
    'table' => 'invitations',
    'user_model' => App\Models\User::class,
    'users_table' => 'users',

    'expiration' => [
        'enabled' => true,
        'days' => 7,
    ],

    'reminders' => [
        'enabled' => true,
        'after_days' => [3, 5],
        'max_reminders' => 2,
    ],

    'notifications' => [
        'invitation' => InvitationSent::class,
        'reminder' => InvitationReminder::class,
        'cancelled' => InvitationCancelledNotification::class,
        'accepted' => InvitationAcceptedNotification::class,
    ],

    'routes' => [
        'enabled' => true,
        'prefix' => 'invitations',
        'middleware' => ['web', 'throttle:60,1'],
    ],

    'redirect' => [
        'accepted' => '/',
        'declined' => '/',
        'expired' => '/',
        'error' => '/',
    ],
];
```

---

## Routes

| Method | URI | Name |
|--------|-----|------|
| GET | `/invitations/{token}` | `invite-only.invitations.show` |
| POST | `/invitations/{token}/accept` | `invite-only.invitations.accept` |
| POST | `/invitations/{token}/decline` | `invite-only.invitations.decline` |

---

## Artisan Commands

```bash
# Send reminders and optionally mark expired
php artisan invite-only:send-reminders [--mark-expired]
```

---

## Factory States

```php
Invitation::factory()->create();                    // Pending
Invitation::factory()->accepted()->create();
Invitation::factory()->declined()->create();
Invitation::factory()->expired()->create();
Invitation::factory()->cancelled()->create();
Invitation::factory()->neverExpires()->create();
Invitation::factory()->expiresInDays(14)->create();
Invitation::factory()->withRole('admin')->create();
Invitation::factory()->withMetadata([...])->create();
Invitation::factory()->invitedBy($userId)->create();
Invitation::factory()->acceptedBy($userId)->create();
Invitation::factory()->needsReminder(3)->create();
```
