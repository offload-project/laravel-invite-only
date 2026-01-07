<p align="center">
    <a href="https://packagist.org/packages/offload-project/laravel-invite-only"><img src="https://img.shields.io/packagist/v/offload-project/laravel-invite-only.svg?style=flat-square" alt="Latest Version on Packagist"></a>
    <a href="https://github.com/offload-project/laravel-invite-only/actions"><img src="https://img.shields.io/github/actions/workflow/status/offload-project/laravel-invite-only/tests.yml?branch=main&style=flat-square" alt="GitHub Tests Action Status"></a>
    <a href="https://packagist.org/packages/offload-project/laravel-invite-only"><img src="https://img.shields.io/packagist/dt/offload-project/laravel-invite-only.svg?style=flat-square" alt="Total Downloads"></a>
</p>

# Laravel Invite Only

A Laravel package for managing user invitations with polymorphic relationships, token-based access, scheduled reminders, and event-driven notifications.

## Features

- **Polymorphic invitations** - Invite users to any model (teams, organizations, projects)
- **Bulk invitations** - Invite multiple users at once with partial failure handling
- **Token-based secure links** - Shareable invitation URLs with secure tokens
- **Status tracking** - Pending, accepted, declined, expired, and cancelled states
- **Automatic reminders** - Scheduled reminder emails for pending invitations
- **Event-driven** - Events fired for all invitation lifecycle changes
- **Structured exceptions** - Error codes and resolution hints for easy debugging

## Requirements

- PHP 8.2+
- Laravel 11.0 or 12.0

## Installation

```bash
composer require offload-project/laravel-invite-only

php artisan vendor:publish --tag="invite-only-config"
php artisan vendor:publish --tag="invite-only-migrations"
php artisan migrate
```

## Quick Start

### 1. Add Traits

```php
// Team.php (or any model that can have invitations)
use OffloadProject\InviteOnly\Traits\HasInvitations;

class Team extends Model
{
    use HasInvitations;
}
```

```php
// User.php
use OffloadProject\InviteOnly\Traits\CanBeInvited;

class User extends Authenticatable
{
    use CanBeInvited;
}
```

### 2. Send Invitations

```php
// Single invitation
$team->invite('user@example.com', [
    'role' => 'member',
    'invited_by' => auth()->user(),
]);

// Bulk invitations
$result = $team->inviteMany(
    ['one@example.com', 'two@example.com', 'three@example.com'],
    ['role' => 'member', 'invited_by' => auth()->user()]
);

$result->successful;  // Collection of created invitations
$result->failed;      // Collection of failures with reasons
```

### 3. Handle Acceptance

```php
use OffloadProject\InviteOnly\Events\InvitationAccepted;

Event::listen(InvitationAccepted::class, function ($event) {
    $team = $event->invitation->invitable;
    $user = $event->user;
    $role = $event->invitation->role;

    $team->users()->attach($user->id, ['role' => $role]);
});
```

### 4. Schedule Reminders (Optional)

```php
// routes/console.php
Schedule::command('invite-only:send-reminders --mark-expired')->daily();
```

## Documentation

- **[Getting Started](docs/getting-started.md)** - Step-by-step tutorial
- **[API Reference](docs/reference.md)** - All methods, events, and configuration
- **[Concepts](docs/concepts.md)** - Lifecycle, architecture, and design decisions

### How-To Guides

- [Team Invitations](docs/howto/team-invitations.md)
- [Custom Notifications](docs/howto/custom-notifications.md)
- [Handling Errors](docs/howto/handling-errors.md)

## Testing

```bash
composer test
```

## License

MIT License. See [LICENSE](LICENSE) for details.
