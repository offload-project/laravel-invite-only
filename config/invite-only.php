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
    | Users Table Name
    |--------------------------------------------------------------------------
    |
    | The name of the users table for foreign key constraints in migrations.
    | This should match the table used by your user model.
    |
    */
    'users_table' => 'users',

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
    | SECURITY NOTE: It is strongly recommended to include rate limiting
    | middleware (e.g., 'throttle:60,1') to prevent brute-force attacks
    | on invitation tokens.
    |
    */
    'routes' => [
        'enabled' => true,
        'prefix' => 'invitations',
        'middleware' => ['web', 'throttle:60,1'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Redirect URLs
    |--------------------------------------------------------------------------
    |
    | Where to redirect users after accepting, declining, when an
    | invitation has expired, or when an error occurs.
    |
    */
    'redirect' => [
        'accepted' => '/',
        'declined' => '/',
        'expired' => '/',
        'error' => '/',
    ],
];
