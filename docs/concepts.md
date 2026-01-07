# Concepts

## Invitation Lifecycle

An invitation moves through a defined set of states:

```
                    ┌─────────────┐
                    │   Created   │
                    │  (Pending)  │
                    └──────┬──────┘
                           │
           ┌───────────────┼───────────────┐
           │               │               │
           ▼               ▼               ▼
    ┌──────────┐    ┌──────────┐    ┌──────────┐
    │ Accepted │    │ Declined │    │ Cancelled│
    └──────────┘    └──────────┘    └──────────┘
                           │
                           ▼
                    ┌──────────┐
                    │ Expired  │
                    └──────────┘
```

- **Pending**: Initial state. Can transition to any other state.
- **Accepted**: User accepted. Terminal state.
- **Declined**: User declined. Terminal state.
- **Cancelled**: Admin cancelled. Terminal state.
- **Expired**: Time ran out. Terminal state (can only come from Pending).

Terminal states cannot transition to other states.

---

## Polymorphic Invitations

Invitations use Laravel's polymorphic relationships, allowing any model to have invitations:

```php
// Teams
$team->invite('user@example.com');

// Organizations
$org->invite('user@example.com');

// Projects
$project->invite('user@example.com');

// Standalone (no parent model)
InviteOnly::invite('user@example.com');
```

The `invitable_type` and `invitable_id` columns store the relationship:

| invitable_type | invitable_id | Meaning |
|----------------|--------------|---------|
| `App\Models\Team` | 1 | Invitation to Team #1 |
| `App\Models\Org` | 5 | Invitation to Org #5 |
| `null` | `null` | Standalone invitation |

---

## Token Security

Each invitation has a cryptographically secure 64-character token:

```php
// Generated using
bin2hex(random_bytes(32));
```

Tokens are:
- Unique across all invitations
- URL-safe (hexadecimal)
- Not guessable (256 bits of entropy)

Rate limiting is enabled by default to prevent brute-force attacks:

```php
'middleware' => ['web', 'throttle:60,1'],
```

---

## Event-Driven Architecture

The package fires events at each lifecycle transition, allowing you to hook into the invitation process without modifying package code:

```php
InvitationCreated   → Send welcome email, log analytics
InvitationAccepted  → Add user to team, grant permissions
InvitationDeclined  → Notify admin, log reason
InvitationCancelled → Clean up, notify invitee
InvitationExpired   → Send "invitation expired" email, offer to resend
```

Events carry the invitation (and user for acceptance):

```php
class InvitationAccepted
{
    public function __construct(
        public Invitation $invitation,
        public ?Model $user,
    ) {}
}
```

---

## Reminder System

Reminders are sent based on configured day thresholds:

```php
'reminders' => [
    'after_days' => [3, 5],  // Send on day 3 and day 5
    'max_reminders' => 2,
],
```

The system tracks `reminder_count` to ensure each invitation gets the right number of reminders:

- Day 0: Invitation sent
- Day 3: First reminder (if still pending)
- Day 5: Second reminder (if still pending)
- Day 7: Expires (if `expiration.days` is 7)

If the scheduler misses a day, the system catches up on the next run without sending duplicate reminders.

---

## Notifications

The package uses Laravel's notification system. Each notification type can be:

1. **Default**: Use the built-in notification class
2. **Custom**: Provide your own class
3. **Disabled**: Set to `null`

```php
'notifications' => [
    'invitation' => InvitationSent::class,      // Built-in
    'reminder' => App\CustomReminder::class,     // Custom
    'cancelled' => null,                         // Disabled
],
```

Notifications are sent to the invitation itself (which implements `Notifiable`), routing to the invitee's email:

```php
$invitation->notify(new InvitationSent($invitation));
// Sends to $invitation->email
```

---

## Structured Exceptions

Exceptions provide machine-readable error codes for API responses:

```php
try {
    InviteOnly::accept($token);
} catch (InvitationException $e) {
    // For humans
    $e->getMessage();   // "This invitation has expired."
    $e->resolution;     // "Create a new invitation..."

    // For machines
    $e->errorCode;      // "INVITATION_EXPIRED"
    $e->toArray();      // Full structured response
}
```

This allows:
- Frontend apps to show localized messages based on error codes
- Logging systems to categorize errors
- API consumers to handle specific cases programmatically

---

## Configuration vs Convention

The package follows "convention over configuration" with sensible defaults:

| Setting | Default | Rationale |
|---------|---------|-----------|
| Expiration | 7 days | Long enough to respond, short enough for security |
| Reminders | Days 3, 5 | Give time before expiration |
| Rate limit | 60/min | Prevent brute force |
| Routes | Enabled | Zero-config for common case |

Override only what you need:

```php
// Only change expiration, keep everything else
'expiration' => [
    'enabled' => true,
    'days' => 14,
],
```

---

## Extending the Package

### Custom Implementation

Swap the entire implementation:

```php
$this->app->singleton(InviteOnlyContract::class, MyInviteOnly::class);
```

### Custom Model

Extend the Invitation model (not recommended unless necessary):

```php
class CustomInvitation extends Invitation
{
    // Add custom logic
}
```

Then bind it in a service provider or use model swapping techniques.

### Adding Metadata

Use the `metadata` column for custom data without schema changes:

```php
$team->invite('user@example.com', [
    'metadata' => [
        'department' => 'Engineering',
        'cost_center' => 'CC-1234',
        'approved_by' => $manager->id,
    ],
]);

// Later
$invitation->metadata['department']; // 'Engineering'
```
