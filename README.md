# Laravel Invite Only

[![Latest Version on Packagist](https://img.shields.io/packagist/v/offload-project/laravel-invite-only.svg?style=flat-square)](https://packagist.org/packages/offload-project/laravel-invite-only)
[![Tests](https://img.shields.io/github/actions/workflow/status/offload-project/laravel-invite-only/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/offload-project/laravel-invite-only/actions/workflows/tests.yml)
[![Build](https://img.shields.io/github/actions/workflow/status/offload-project/laravel-invite-only/release.yml?label=build&style=flat-square)](https://github.com/offload-project/laravel-invite-only/actions/workflows/release.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/offload-project/laravel-invite-only.svg?style=flat-square)](https://packagist.org/packages/offload-project/laravel-invite-only)
[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg?style=flat-square)](LICENSE.md)

A Laravel package for managing user invitations with polymorphic relationships, token-based access, scheduled reminders,
and event-driven notifications.

## Features

- **Polymorphic invitations** - Invite users to any model (teams, organizations, projects)
- **Bulk invitations** - Invite multiple users at once with partial failure handling
- **Token-based secure links** - Shareable invitation URLs with secure tokens
- **Status tracking** - Pending, accepted, declined, expired, and cancelled states
- **Automatic reminders** - Scheduled reminder emails for pending invitations
- **Event-driven** - Events fired for all invitation lifecycle changes
- **Translatable notifications** - All notification messages customizable via language files
- **Structured exceptions** - Error codes and resolution hints for easy debugging

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
    - [Add Traits](#1-add-traits)
    - [Send Invitations](#2-send-invitations)
    - [Handle Acceptance](#3-handle-acceptance)
    - [Schedule Reminders (Optional)](#4-schedule-reminders-optional)
- [Full Documentation](#full-documentation)
- [AI Coding Assistant Skill](#ai-coding-assistant-skill)
- [Testing](#testing)
- [Contributing](#contributing)
- [License](#license)

## Requirements

- PHP 8.2+
- Laravel 11/12/13

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

If a model both sends and receives invitations (e.g. user-to-user friend
invitations), apply both traits. The two traits each define an
`acceptedInvitations()` method, so use PHP's trait conflict resolution:

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

In v3.0 the `HasInvitations::acceptedInvitations()` helper will be removed in
favour of `getAcceptedInvitations()`, eliminating the conflict — at which point
the `insteadof`/`as` clauses can be dropped.

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

## Full Documentation

- **[Getting Started](docs/getting-started.md)** - Step-by-step tutorial
- **[API Reference](docs/reference.md)** - All methods, events, and configuration
- **[Concepts](docs/concepts.md)** - Lifecycle, architecture, and design decisions

### How-To Guides

- [Team Invitations](docs/howto/team-invitations.md)
- [Custom Notifications](docs/howto/custom-notifications.md)
- [Handling Errors](docs/howto/handling-errors.md)

## AI Coding Assistant Skill

This package ships a [Laravel Boost](https://skills.laravel.cloud/) skill so coding assistants (Claude Code, Cursor,
etc.) follow the package's conventions when generating code. Install it in your app with:

```bash
php artisan boost:add-skill offload-project/laravel-invite-only
```

The skill source lives at [`skills/SKILL.md`](skills/SKILL.md).

## Testing

```bash
composer test
```

## Contributing

Contributions are welcome! Please see the documents below before getting started.

- [Contributing Guide](CONTRIBUTING.md) — setup, workflow, commit conventions, and PR process
- [Code of Conduct](CODE_OF_CONDUCT.md) — expectations for participation in this project

## Security

- [Security Policy](SECURITY.md) — how to report a vulnerability privately

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
