# How to Customize Notifications

## Disable a Notification

Set the notification class to `null` in your config:

```php
// config/invite-only.php
'notifications' => [
    'invitation' => InvitationSent::class,
    'reminder' => null,  // Disabled
    'cancelled' => null, // Disabled
    'accepted' => InvitationAcceptedNotification::class,
],
```

## Create a Custom Notification

1. Generate a new notification:

```bash
php artisan make:notification CustomInvitationNotification
```

2. Implement it with the invitation data:

```php
<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use OffloadProject\InviteOnly\Models\Invitation;

class CustomInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Invitation $invitation
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inviter = $this->invitation->inviter;
        $team = $this->invitation->invitable;

        return (new MailMessage)
            ->subject("{$inviter->name} invited you to join {$team->name}")
            ->greeting("Hello!")
            ->line("You've been invited to join {$team->name} as a {$this->invitation->role}.")
            ->action('Accept Invitation', $this->invitation->getAcceptUrl())
            ->line("Or decline: {$this->invitation->getDeclineUrl()}")
            ->line("This invitation expires on {$this->invitation->expires_at->format('M j, Y')}.");
    }
}
```

3. Register it in your config:

```php
// config/invite-only.php
'notifications' => [
    'invitation' => App\Notifications\CustomInvitationNotification::class,
    // ...
],
```

## Add Slack or Other Channels

Extend the `via()` method to include additional channels:

```php
public function via(object $notifiable): array
{
    return ['mail', 'slack', 'database'];
}

public function toSlack(object $notifiable): SlackMessage
{
    return (new SlackMessage)
        ->content("New invitation for {$this->invitation->email}");
}

public function toArray(object $notifiable): array
{
    return [
        'invitation_id' => $this->invitation->id,
        'email' => $this->invitation->email,
        'team' => $this->invitation->invitable?->name,
    ];
}
```

## Queue Notifications

All default notifications use the `Queueable` trait. Configure your queue connection in `.env`:

```
QUEUE_CONNECTION=redis
```

Then run the queue worker:

```bash
php artisan queue:work
```
