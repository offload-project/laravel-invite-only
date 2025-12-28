<?php

declare(strict_types=1);

use OffloadProject\InviteOnly\Notifications\InvitationAcceptedNotification;
use OffloadProject\InviteOnly\Notifications\InvitationCancelledNotification;
use OffloadProject\InviteOnly\Notifications\InvitationReminder;
use OffloadProject\InviteOnly\Notifications\InvitationSent;

return [
    /*
    |--------------------------------------------------------------------------
    | Invitations Table Name
    |--------------------------------------------------------------------------
    |
    | The name of the database table that will store invitations.
    |
    */
    'table' => 'invitations',

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The user model class used for invited_by and accepted_by relationships.
    |
    */
    'user_model' => App\Models\User::class,

    /*
    |--------------------------------------------------------------------------
    | Expiration Settings
    |--------------------------------------------------------------------------
    |
    | Configure whether invitations expire and after how many days.
    |
    */
    'expiration' => [
        'enabled' => true,
        'days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Reminder Settings
    |--------------------------------------------------------------------------
    |
    | Configure automatic reminder emails for pending invitations.
    | The 'after_days' array specifies when reminders should be sent
    | (e.g., [3, 5] means send reminders after 3 and 5 days).
    |
    */
    'reminders' => [
        'enabled' => true,
        'after_days' => [3, 5],
        'max_reminders' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Classes
    |--------------------------------------------------------------------------
    |
    | Customize the notification classes used for different events.
    | Set to null to disable a specific notification.
    |
    */
    'notifications' => [
        'invitation' => InvitationSent::class,
        'reminder' => InvitationReminder::class,
        'cancelled' => InvitationCancelledNotification::class,
        'accepted' => InvitationAcceptedNotification::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Settings
    |--------------------------------------------------------------------------
    |
    | Configure the routes for accepting and declining invitations.
    |
    */
    'routes' => [
        'enabled' => true,
        'prefix' => 'invitations',
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Redirect URLs
    |--------------------------------------------------------------------------
    |
    | Where to redirect users after accepting, declining, or when an
    | invitation has expired.
    |
    */
    'redirect' => [
        'accepted' => '/',
        'declined' => '/',
        'expired' => '/',
    ],
];
