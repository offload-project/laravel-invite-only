# How to Handle Invitation Errors

## Catching Specific Exceptions

```php
use OffloadProject\InviteOnly\Exceptions\InvalidInvitationException;
use OffloadProject\InviteOnly\Exceptions\InvitationAlreadyAcceptedException;
use OffloadProject\InviteOnly\Exceptions\InvitationExpiredException;
use OffloadProject\InviteOnly\Facades\InviteOnly;

try {
    $invitation = InviteOnly::accept($token, $user);
} catch (InvitationExpiredException $e) {
    // Offer to resend a new invitation
    return redirect()->route('invitation.expired', [
        'email' => $e->invitation->email,
    ]);
} catch (InvitationAlreadyAcceptedException $e) {
    // Redirect to the resource they already have access to
    return redirect()->route('teams.show', $e->invitation->invitable_id);
} catch (InvalidInvitationException $e) {
    // Log for debugging, show generic error to user
    Log::warning('Invalid invitation attempt', $e->toArray());

    return back()->with('error', 'This invitation is no longer valid.');
}
```

## Using Error Codes in API Responses

```php
public function accept(Request $request, string $token)
{
    try {
        $invitation = InviteOnly::accept($token, $request->user());

        return response()->json([
            'message' => 'Invitation accepted',
            'team_id' => $invitation->invitable_id,
        ]);
    } catch (InvitationException $e) {
        return response()->json([
            'error' => $e->errorCode,
            'message' => $e->getMessage(),
            'resolution' => $e->resolution,
        ], 422);
    }
}
```

## Error Code Reference

| Error Code | Meaning | Suggested Action |
|------------|---------|------------------|
| `INVITATION_TOKEN_NOT_FOUND` | Token doesn't exist | Check URL, may be deleted |
| `INVITATION_NOT_FOUND` | ID doesn't exist | Verify invitation ID |
| `INVITATION_EXPIRED` | Past expiration date | Create new invitation |
| `INVITATION_ALREADY_ACCEPTED` | Already accepted | User has access |
| `INVITATION_CANCELLED` | Was cancelled | Create new invitation |
| `INVITATION_DECLINED` | Was declined | Create new invitation |
| `INVITATION_NOT_DECLINABLE` | Not in pending state | Check status first |
| `INVITATION_NOT_CANCELLABLE` | Not in pending state | Check status first |
| `INVITATION_NOT_RESENDABLE` | Expired or not pending | Check `isValid()` first |

## Validating Before Acting

Avoid exceptions by checking state first:

```php
$invitation = InviteOnly::find($token);

if ($invitation === null) {
    return back()->with('error', 'Invitation not found.');
}

if (!$invitation->isValid()) {
    if ($invitation->isExpired()) {
        return back()->with('error', 'This invitation has expired.');
    }
    if ($invitation->isAccepted()) {
        return redirect()->route('teams.show', $invitation->invitable_id);
    }
    return back()->with('error', 'This invitation is no longer valid.');
}

// Safe to accept
$invitation = InviteOnly::accept($token, $user);
```

## Global Exception Handling

In `app/Exceptions/Handler.php` (Laravel 10) or `bootstrap/app.php` (Laravel 11+):

```php
use OffloadProject\InviteOnly\Exceptions\InvitationException;

// Laravel 11+
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (InvitationException $e) {
        if (request()->expectsJson()) {
            return response()->json($e->toArray(), 422);
        }

        return back()->with('error', $e->getMessage());
    });
})
```
