# How to Set Up Team Invitations

## Basic Setup

Add the trait to your Team model:

```php
use OffloadProject\InviteOnly\Traits\HasInvitations;

class Team extends Model
{
    use HasInvitations;
}
```

## Invite a User

```php
$team->invite('user@example.com', [
    'role' => 'member',
    'invited_by' => auth()->user(),
    'metadata' => [
        'department' => 'Engineering',
        'permissions' => ['read', 'write'],
    ],
]);
```

## Invite Multiple Users

```php
$result = $team->inviteMany([
    'alice@example.com',
    'bob@example.com',
    'carol@example.com',
], [
    'role' => 'member',
    'invited_by' => auth()->user(),
]);

// Check results
if ($result->hasFailures()) {
    foreach ($result->failed as $failure) {
        Log::warning("Failed to invite {$failure['email']}: {$failure['reason']}");
    }
}

// Get successful invitations
foreach ($result->successful as $invitation) {
    // $invitation->email, $invitation->token, etc.
}
```

### Handling Duplicates

By default, emails with pending invitations are skipped:

```php
$result = $team->inviteMany(['existing@example.com', 'new@example.com']);
// existing@example.com → failed (Pending invitation already exists)
// new@example.com → successful
```

To allow duplicate invitations:

```php
$result = $team->inviteMany($emails, ['skip_duplicates' => false]);
```

## List Pending Invitations

```php
// In a controller
public function invitations(Team $team)
{
    return view('teams.invitations', [
        'pending' => $team->pendingInvitations(),
        'accepted' => $team->acceptedInvitations(),
        'stats' => $team->getInvitationStats(),
    ]);
}
```

## Cancel an Invitation

```php
// By email
$team->cancelInvitation('user@example.com', notify: true);

// By invitation object
InviteOnly::cancel($invitation, notify: true);
```

## Resend an Invitation

```php
// By email
$team->resendInvitation('user@example.com');

// By invitation object
InviteOnly::resend($invitation);
```

## Check for Existing Invitation

```php
if ($team->hasInvitationFor('user@example.com')) {
    return back()->with('error', 'This user already has a pending invitation.');
}
```

## Prevent Duplicate Invitations

```php
public function invite(Request $request, Team $team)
{
    $email = $request->validated('email');

    // Check for existing pending invitation
    if ($team->hasInvitationFor($email)) {
        return back()->with('error', 'Already invited.');
    }

    // Check if already a member
    if ($team->users()->where('email', $email)->exists()) {
        return back()->with('error', 'Already a member.');
    }

    $team->invite($email, [
        'role' => $request->validated('role'),
        'invited_by' => $request->user(),
    ]);

    return back()->with('success', 'Invitation sent!');
}
```

## Handle Acceptance in Event Listener

```php
use OffloadProject\InviteOnly\Events\InvitationAccepted;

class AddUserToTeam
{
    public function handle(InvitationAccepted $event): void
    {
        $team = $event->invitation->invitable;
        $user = $event->user;
        $role = $event->invitation->role ?? 'member';

        $team->users()->attach($user->id, ['role' => $role]);
    }
}
```

## Display Invitation Stats

```php
$stats = $team->getInvitationStats();

// Returns:
// [
//     'total' => 10,
//     'pending' => 3,
//     'accepted' => 5,
//     'declined' => 1,
//     'expired' => 1,
//     'cancelled' => 0,
// ]
```
