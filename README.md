<p align="center">
    <a href="https://packagist.org/packages/offload-project/laravel-invite-only"><img src="https://img.shields.io/packagist/v/offload-project/laravel-invite-only.svg?style=flat-square" alt="Latest Version on Packagist"></a>
    <a href="https://github.com/offload-project/laravel-invite-only/actions"><img src="https://img.shields.io/github/actions/workflow/status/offload-project/laravel-invite-only/tests.yml?branch=main&style=flat-square" alt="GitHub Tests Action Status"></a>
    <a href="https://packagist.org/packages/offload-project/laravel-invite-only"><img src="https://img.shields.io/packagist/dt/offload-project/laravel-invite-only.svg?style=flat-square" alt="Total Downloads"></a>
</p>

# Laravel Invite Only

A Laravel package for managing user invitations with polymorphic relationships, token-based access, scheduled reminders,
and event-driven notifications.

## Features

- **Polymorphic invitations** - Invite users to any model (teams, organizations, projects, etc.)
- **Token-based secure links** - Shareable invitation URLs with secure tokens
- **Status tracking** - Track pending, accepted, declined, expired, and cancelled invitations
- **Automatic reminders** - Scheduled command to send reminder emails
- **Resend invitations** - Easily resend invitation emails
- **Cancel with notification** - Cancel invitations with optional email notification
- **Event-driven** - Events fired for all invitation lifecycle changes
- **Configurable** - Customize expiration, reminders, notifications, and routes

## Requirements

- PHP 8.4+
- Laravel 11.0 or 12.0

## Installation

```bash
composer require offload-project/laravel-invite-only
```

Publish the configuration and migrations:

```bash
php artisan vendor:publish --tag="invite-only-config"
php artisan vendor:publish --tag="invite-only-migrations"
php artisan migrate
```

## Configuration

The configuration file `config/invite-only.php` allows you to customize:

```php
return [
    // Database table name
    'table' => 'invitations',

    // User model for relationships
    'user_model' => App\Models\User::class,

    // Invitation expiration
    'expiration' => [
        'enabled' => true,
        'days' => 7,
    ],

    // Reminder settings
    'reminders' => [
        'enabled' => true,
        'after_days' => [3, 5],  // Send reminders after 3 and 5 days
        'max_reminders' => 2,
    ],

    // Custom notification classes
    'notifications' => [
        'invitation' => InvitationSent::class,
        'reminder' => InvitationReminder::class,
        'cancelled' => InvitationCancelledNotification::class,
        'accepted' => InvitationAcceptedNotification::class,
    ],

    // Route settings
    'routes' => [
        'enabled' => true,
        'prefix' => 'invitations',
        'middleware' => ['web'],
    ],

    // Redirect URLs after actions
    'redirect' => [
        'accepted' => '/',
        'declined' => '/',
        'expired' => '/',
    ],
];
```

## Usage

### Basic Usage with Facade

```php
use OffloadProject\InviteOnly\Facades\InviteOnly;

// Create a standalone invitation
$invitation = InviteOnly::invite('user@example.com');

// Create an invitation for a specific model (team, organization, etc.)
$invitation = InviteOnly::invite('user@example.com', $team, [
    'role' => 'member',
    'metadata' => ['department' => 'Engineering'],
    'invited_by' => $currentUser,
]);

// Accept an invitation
$invitation = InviteOnly::accept($token, $user);

// Decline an invitation
$invitation = InviteOnly::decline($token);

// Cancel an invitation (with optional notification)
InviteOnly::cancel($invitation, notify: true);

// Resend an invitation
InviteOnly::resend($invitation);

// Find invitations
$invitation = InviteOnly::find($token);
$invitation = InviteOnly::findByEmail('user@example.com', $team);

// Query invitations
$pending = InviteOnly::pending($team);
$accepted = InviteOnly::accepted($team);
$expired = InviteOnly::expired();
```

### Using Traits

#### HasInvitations Trait (for Team, Organization, etc.)

Add the `HasInvitations` trait to models that can have invitations:

```php
use OffloadProject\InviteOnly\Traits\HasInvitations;

class Team extends Model
{
    use HasInvitations;
}
```

Then use the convenient methods:

```php
// Invite a user to the team
$team->invite('user@example.com', [
    'role' => 'admin',
    'invited_by' => $currentUser,
]);

// Get pending invitations
$pending = $team->pendingInvitations();

// Get accepted invitations
$accepted = $team->acceptedInvitations();

// Check if email has pending invitation
if ($team->hasInvitationFor('user@example.com')) {
    // ...
}

// Cancel invitation by email
$team->cancelInvitation('user@example.com', notify: true);

// Resend invitation
$team->resendInvitation('user@example.com');

// Get invitation statistics
$stats = $team->getInvitationStats();
// ['total' => 10, 'pending' => 3, 'accepted' => 5, 'declined' => 1, 'expired' => 1, 'cancelled' => 0]
```

#### CanBeInvited Trait (for User model)

Add the `CanBeInvited` trait to your User model:

```php
use OffloadProject\InviteOnly\Traits\CanBeInvited;

class User extends Authenticatable
{
    use CanBeInvited;
}
```

Then use the convenient methods:

```php
// Get all invitations received by this user's email
$invitations = $user->receivedInvitations;

// Get invitations this user has accepted
$accepted = $user->acceptedInvitations;

// Get invitations sent by this user
$sent = $user->sentInvitations;

// Accept an invitation
$invitation = $user->acceptInvitation($token);

// Decline an invitation
$invitation = $user->declineInvitation($token);

// Check for pending invitations
if ($user->hasPendingInvitation($team)) {
    // ...
}

// Get all pending invitations
$pending = $user->getPendingInvitations();
```

### Scheduled Reminders

Add the reminder command to your scheduler in `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('invite-only:send-reminders --mark-expired')->daily();
```

This will:

- Send reminder emails based on your `reminders.after_days` configuration
- Optionally mark expired invitations when using `--mark-expired`

### Events

The package fires events for all invitation lifecycle changes:

| Event                 | Payload                | Description                             |
|-----------------------|------------------------|-----------------------------------------|
| `InvitationCreated`   | `$invitation`          | When a new invitation is created        |
| `InvitationAccepted`  | `$invitation`, `$user` | When an invitation is accepted          |
| `InvitationDeclined`  | `$invitation`          | When an invitation is declined          |
| `InvitationCancelled` | `$invitation`          | When an invitation is cancelled         |
| `InvitationExpired`   | `$invitation`          | When an invitation is marked as expired |

Listen to these events in your `EventServiceProvider`:

```php
use OffloadProject\InviteOnly\Events\InvitationAccepted;

protected $listen = [
    InvitationAccepted::class => [
        AddUserToTeamListener::class,
    ],
];
```

### Custom Notifications

You can customize notifications by extending the default classes or creating your own:

```php
// config/invite-only.php
'notifications' => [
    'invitation' => App\Notifications\CustomInvitationNotification::class,
    // ...
],
```

Set a notification to `null` to disable it:

```php
'notifications' => [
    'reminder' => null,  // Disable reminder notifications
],
```

### Routes

The package registers the following routes by default:

| Method | URI                            | Name                  | Description              |
|--------|--------------------------------|-----------------------|--------------------------|
| GET    | `/invitations/{token}`         | `invitations.show`    | View/redirect invitation |
| POST   | `/invitations/{token}/accept`  | `invitations.accept`  | Accept invitation        |
| POST   | `/invitations/{token}/decline` | `invitations.decline` | Decline invitation       |

You can disable automatic route registration and define your own:

```php
// config/invite-only.php
'routes' => [
    'enabled' => false,
],
```

## Invitation Model

The `Invitation` model provides many helpful methods:

```php
// Status checks
$invitation->isPending();
$invitation->isAccepted();
$invitation->isDeclined();
$invitation->isExpired();
$invitation->isCancelled();
$invitation->isValid();  // pending AND not expired

// Get URLs
$invitation->getAcceptUrl();
$invitation->getDeclineUrl();

// Relationships
$invitation->invitable;      // The model being invited to (Team, etc.)
$invitation->inviter;        // User who sent the invitation
$invitation->acceptedByUser; // User who accepted

// Query scopes
Invitation::pending()->get();
Invitation::accepted()->get();
Invitation::valid()->get();
Invitation::forEmail('user@example.com')->get();
Invitation::forInvitable($team)->get();
```

## Testing

```bash
composer test
```

## Static Analysis

```bash
composer analyse
```

## Code Style

```bash
composer pint
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
