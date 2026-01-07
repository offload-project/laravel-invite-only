# Getting Started

This tutorial walks you through setting up team invitations from scratch. By the end, you'll have a working invitation system where users can invite others to join their teams.

## Prerequisites

- A Laravel 11+ application
- A `User` model with email field
- A `Team` model (or similar) that users can be invited to

## Step 1: Install the Package

```bash
composer require offload-project/laravel-invite-only
```

## Step 2: Publish and Run Migrations

```bash
php artisan vendor:publish --tag="invite-only-config"
php artisan vendor:publish --tag="invite-only-migrations"
php artisan migrate
```

This creates the `invitations` table with all necessary fields.

## Step 3: Add the Trait to Your Team Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OffloadProject\InviteOnly\Traits\HasInvitations;

class Team extends Model
{
    use HasInvitations;
}
```

## Step 4: Add the Trait to Your User Model

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use OffloadProject\InviteOnly\Traits\CanBeInvited;

class User extends Authenticatable
{
    use CanBeInvited;
}
```

## Step 5: Create Your First Invitation

In a controller or anywhere in your application:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\Request;

class TeamInvitationController extends Controller
{
    public function invite(Request $request, Team $team)
    {
        $request->validate([
            'email' => 'required|email',
            'role' => 'required|in:member,admin',
        ]);

        $invitation = $team->invite($request->email, [
            'role' => $request->role,
            'invited_by' => $request->user(),
        ]);

        return back()->with('success', "Invitation sent to {$request->email}");
    }
}
```

The invitee receives an email with accept/decline links automatically.

## Step 6: Handle the Accepted Invitation

When someone accepts an invitation, you'll want to add them to the team. Listen for the `InvitationAccepted` event:

```php
<?php

namespace App\Listeners;

use OffloadProject\InviteOnly\Events\InvitationAccepted;

class AddUserToTeam
{
    public function handle(InvitationAccepted $event): void
    {
        $invitation = $event->invitation;
        $user = $event->user;
        $team = $invitation->invitable;

        // Add the user to the team with their assigned role
        $team->users()->attach($user->id, [
            'role' => $invitation->role,
        ]);
    }
}
```

Register the listener in your `AppServiceProvider` or `EventServiceProvider`:

```php
use Illuminate\Support\Facades\Event;
use OffloadProject\InviteOnly\Events\InvitationAccepted;
use App\Listeners\AddUserToTeam;

Event::listen(InvitationAccepted::class, AddUserToTeam::class);
```

## Step 7: Set Up Automatic Reminders (Optional)

Add the reminder command to your scheduler in `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('invite-only:send-reminders --mark-expired')->daily();
```

This sends reminder emails to pending invitations and marks expired ones.

## What's Next?

- [How to customize notifications](howto/custom-notifications.md)
- [How to handle invitation errors](howto/handling-errors.md)
- [API Reference](reference.md)
- [Understanding the invitation lifecycle](concepts.md)
