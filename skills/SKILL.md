---
name: Laravel Invite Only
description: Conventions and APIs for the offload-project/laravel-invite-only package — polymorphic invitations, token acceptance, bulk invites, scheduled reminders, and event-driven hooks.
compatible_agents:
  - Claude Code
  - Cursor
tags:
  - laravel
  - php
  - invitations
  - eloquent
  - polymorphic
  - notifications
  - events
---

## Context

`offload-project/laravel-invite-only` is a Laravel 11/12/13 package (PHP 8.2+) for managing user invitations against any model via polymorphic relationships. It ships:

- An `Invitation` Eloquent model with status lifecycle (`pending`, `accepted`, `declined`, `expired`, `cancelled`) backed by an `InvitationStatus` enum.
- Two traits: `HasInvitations` (for models that issue invitations — Team, Organization, Project) and `CanBeInvited` (for the User model).
- An `InviteOnly` facade that wraps token generation, event dispatch, and notification sending.
- Events for every lifecycle transition: `InvitationCreated`, `InvitationAccepted`, `InvitationDeclined`, `InvitationCancelled`, `InvitationExpired`.
- Structured exceptions: `InvalidInvitationException`, `InvitationAlreadyAcceptedException`, `InvitationExpiredException`.
- A `invite-only:send-reminders` Artisan command for scheduled reminder emails and expiration sweeps.

Apply this skill when working in a Laravel app that has `offload-project/laravel-invite-only` in `composer.json`, or when the user asks for help with `InviteOnly`, `HasInvitations`, `CanBeInvited`, the `Invitation` model, or invitation flows in this package.

## Rules

### Trait usage

1. Apply `HasInvitations` to any model that can issue invitations (Team, Organization, Project, Account). Apply `CanBeInvited` to the User model that receives them.
2. If a single model both sends and receives invitations (e.g. user-to-user friend invites), use both traits with PHP trait conflict resolution — the two traits each define an `acceptedInvitations()` method that collides. See the example below.
3. Prefer `getAcceptedInvitations()` over the deprecated `acceptedInvitations()` helper on `HasInvitations`. The deprecated method will be removed in v3.0.

### Status & enum

4. Use the `InvitationStatus` enum (`InvitationStatus::Pending`, `Accepted`, `Declined`, `Expired`, `Cancelled`). Do **not** use the deprecated `Invitation::STATUS_*` string constants; they are kept only for backwards compatibility and will be removed.
5. Check terminal states via `$status->isTerminal()` rather than chaining `||` against individual cases.

### Creating invitations

6. Create invitations through `$invitable->invite()` / `$invitable->inviteMany()` (preferred) or the `InviteOnly` facade. Do **not** call `Invitation::create()` directly — the facade handles token generation, expiration defaults, the `InvitationCreated` event, and the outbound notification.
7. Pass the invitable model via the trait method (`$team->invite(...)`), or as the second argument to `InviteOnly::invite($email, $invitable, $options)`. For invitations not tied to any model (e.g. open-platform signup), pass `null`.
8. For bulk invites, use `inviteMany()` and inspect the returned `BulkInvitationResult` — `$result->successful` (Collection of `Invitation`) and `$result->failed` (Collection of `['email' => ..., 'reason' => ...]`). It supports partial failure; do not wrap it in a try/catch expecting an exception.
9. `inviteMany()` deduplicates against existing pending invitations by default. Pass `'skip_duplicates' => false` only when you intentionally want duplicate pending invites.

### Accepting / declining / cancelling

10. Accept by calling `InviteOnly::accept($token, $user)` (or `Invitation::accept` via facade). Always pass the authenticated `User` so `accepted_by` is recorded.
11. Catch the typed exceptions individually when handling user-facing flows — `InvitationExpiredException`, `InvitationAlreadyAcceptedException`, `InvalidInvitationException` — to produce specific error messages. Do not catch the bare base `InvitationException` unless you intentionally want to collapse all failure modes.
12. Wire the actual "do something on acceptance" logic (attaching a user to a team, granting a role, etc.) in an `InvitationAccepted` event listener, **not** inline at every call site. The facade fires the event for you.

### Configuration & customization

13. Customize notifications by overriding the `invite-only.notifications.{invitation,reminder,cancelled,accepted}` config entries. Setting any of them to `null` disables that notification. Do not edit the package's notification classes directly.
14. Adjust expiration window via `invite-only.expiration.days` (default 7). Set `invite-only.expiration.enabled` to `false` for non-expiring invitations.
15. Configure reminders via `invite-only.reminders.after_days` (e.g. `[3, 5]`) and `max_reminders`. Reminders only fire if `reminders.enabled` is true.
16. The default routes are mounted at `/invitations` with `['web', 'throttle:60,1']` middleware. Keep the throttle (or stricter) — invitation tokens are otherwise susceptible to brute force. Disable the package routes (`routes.enabled => false`) only if you're providing your own.

### Scheduling

17. Schedule the bundled command to run daily so reminders go out and expired invitations get marked:

    ```php
    Schedule::command('invite-only:send-reminders --mark-expired')->daily();
    ```

    Without `--mark-expired`, pending invitations past `expires_at` stay in `pending` status until something else marks them.

### Mass assignment / model

18. The `Invitation` model is `final`. To extend behavior, listen to events or wrap calls — do not try to subclass it.
19. All migration columns are in `$fillable`. Setting lifecycle fields (`accepted_at`, `accepted_by`, `declined_at`, `cancelled_at`, `last_sent_at`, `reminder_count`) directly via `update()` is allowed but discouraged — prefer the `markAs*()` helpers so casts and side effects stay consistent.
20. Use `$invitation->isValid()` (pending **and** not expired) when gating "can this token still be used" checks. `isPending()` alone is not sufficient.

## Examples

### Basic setup

```php
// app/Models/Team.php
use OffloadProject\InviteOnly\Traits\HasInvitations;

class Team extends Model
{
    use HasInvitations;
}
```

```php
// app/Models/User.php
use OffloadProject\InviteOnly\Traits\CanBeInvited;

class User extends Authenticatable
{
    use CanBeInvited;
}
```

### Model that both sends and receives invitations

```php
use OffloadProject\InviteOnly\Traits\CanBeInvited;
use OffloadProject\InviteOnly\Traits\HasInvitations;

class User extends Authenticatable
{
    use HasInvitations, CanBeInvited {
        CanBeInvited::acceptedInvitations insteadof HasInvitations;
        HasInvitations::acceptedInvitations as acceptedInvitationsToModel;
    }
}
```

In v3.0 `HasInvitations::acceptedInvitations()` will be removed in favour of `getAcceptedInvitations()`, eliminating the conflict — at which point the `insteadof` / `as` clauses can be dropped.

### Sending invitations

```php
$invitation = $team->invite('user@example.com', [
    'role' => 'member',
    'invited_by' => auth()->user(),
    'metadata' => ['source' => 'team-settings'],
]);
```

### Bulk invitations with partial-failure handling

```php
$result = $team->inviteMany(
    ['one@example.com', 'two@example.com', 'bad-email'],
    ['role' => 'member', 'invited_by' => auth()->user()],
);

foreach ($result->successful as $invitation) {
    // send to UI, log, etc.
}

foreach ($result->failed as $failure) {
    Log::warning('Skipped invite', $failure); // ['email' => ..., 'reason' => ...]
}
```

### Accepting an invitation

```php
use OffloadProject\InviteOnly\Exceptions\InvalidInvitationException;
use OffloadProject\InviteOnly\Exceptions\InvitationAlreadyAcceptedException;
use OffloadProject\InviteOnly\Exceptions\InvitationExpiredException;
use OffloadProject\InviteOnly\Facades\InviteOnly;

try {
    $invitation = InviteOnly::accept($token, auth()->user());
} catch (InvitationExpiredException) {
    return redirect()->route('login')->withErrors(['invite' => 'This invitation has expired.']);
} catch (InvitationAlreadyAcceptedException) {
    return redirect()->route('dashboard');
} catch (InvalidInvitationException $e) {
    return redirect()->route('login')->withErrors(['invite' => $e->getMessage()]);
}
```

### Wiring side effects via the event

```php
use Illuminate\Support\Facades\Event;
use OffloadProject\InviteOnly\Events\InvitationAccepted;

Event::listen(InvitationAccepted::class, function (InvitationAccepted $event): void {
    $team = $event->invitation->invitable; // Team|Organization|null
    $user = $event->user;
    $role = $event->invitation->role;

    if ($team !== null && $user !== null) {
        $team->users()->attach($user->id, ['role' => $role]);
    }
});
```

### Custom notification

```php
// config/invite-only.php
'notifications' => [
    'invitation' => App\Notifications\TeamInvitationSent::class,
    'reminder'   => App\Notifications\TeamInvitationReminder::class,
    'cancelled'  => null, // disabled
    'accepted'   => App\Notifications\TeamInvitationAccepted::class,
],
```

### Status checks with the enum

```php
use OffloadProject\InviteOnly\Enums\InvitationStatus;

if ($invitation->status === InvitationStatus::Pending) { /* ... */ }

if ($invitation->status->isTerminal()) {
    // accepted | declined | expired | cancelled
}
```

### Scheduled reminders + expiration sweep

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('invite-only:send-reminders --mark-expired')->daily();
```

## Anti-patterns

- ❌ `Invitation::create([...])` — bypasses token generation, the `InvitationCreated` event, and the outbound notification. Always go through the facade or trait method.
- ❌ Using `Invitation::STATUS_PENDING` (and the other `STATUS_*` constants). Deprecated — use `InvitationStatus::Pending` etc.
- ❌ Using `acceptedInvitations()` from `HasInvitations`. Deprecated — use `getAcceptedInvitations()`.
- ❌ Subclassing `Invitation`. The model is `final`; extend behavior via events or wrapper services.
- ❌ Catching `\Throwable` or `\Exception` around `InviteOnly::accept()`. Catch the typed exceptions so each failure mode produces a tailored response.
- ❌ Doing "attach user to team / grant role" work inline at the acceptance route. Move it to an `InvitationAccepted` listener so manual acceptance, console flows, and webhook acceptance all behave the same.
- ❌ Disabling the `throttle` middleware on package routes. Invitation tokens are 64-char hex; without a throttle they are still brute-forceable at high rates.
- ❌ Editing files inside `vendor/offload-project/laravel-invite-only`. All extension points (notifications, expiration, routes, redirects) are exposed via `config/invite-only.php`.

## References

- Repository: <https://github.com/offload-project/laravel-invite-only>
- Getting Started: <https://github.com/offload-project/laravel-invite-only/blob/main/docs/getting-started.md>
- API Reference: <https://github.com/offload-project/laravel-invite-only/blob/main/docs/reference.md>
- Concepts (lifecycle, architecture): <https://github.com/offload-project/laravel-invite-only/blob/main/docs/concepts.md>
- How-to: Team Invitations — <https://github.com/offload-project/laravel-invite-only/blob/main/docs/howto/team-invitations.md>
- How-to: Custom Notifications — <https://github.com/offload-project/laravel-invite-only/blob/main/docs/howto/custom-notifications.md>
- How-to: Handling Errors — <https://github.com/offload-project/laravel-invite-only/blob/main/docs/howto/handling-errors.md>
