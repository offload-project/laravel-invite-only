<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Invitation Sent Notification
    |--------------------------------------------------------------------------
    */
    'invitation' => [
        'subject' => "You've Been Invited!",
        'greeting' => 'Hello!',
        'line_with_name' => 'You have been invited to join :name.',
        'line_without_name' => 'You have been invited to join us.',
        'action_line' => 'Click the button below to accept your invitation.',
        'action_text' => 'Accept Invitation',
        'footer' => 'If you did not expect this invitation, you can ignore this email.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Invitation Reminder Notification
    |--------------------------------------------------------------------------
    */
    'reminder' => [
        'subject' => 'Reminder: Your Invitation is Waiting',
        'greeting' => 'Hello!',
        'line_with_name' => 'Just a friendly reminder that you have a pending invitation to join :name.',
        'line_without_name' => 'Just a friendly reminder that you have a pending invitation waiting for you.',
        'date_format' => 'F j, Y',
        'expires_line' => 'This invitation will expire on :date.',
        'action_text' => 'Accept Invitation',
        'footer' => 'If you are not interested, you can safely ignore this email.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Invitation Accepted Notification
    |--------------------------------------------------------------------------
    */
    'accepted' => [
        'subject' => 'Invitation Accepted!',
        'greeting' => 'Good news!',
        'line_with_name' => ':email has accepted your invitation to join :name.',
        'line_without_name' => ':email has accepted your invitation.',
        'team_line' => 'They are now part of your team.',
        'action_text' => 'View Dashboard',
    ],

    /*
    |--------------------------------------------------------------------------
    | Invitation Cancelled Notification
    |--------------------------------------------------------------------------
    */
    'cancelled' => [
        'subject' => 'Invitation Cancelled',
        'greeting' => 'Hello!',
        'line_with_name' => 'Your invitation to join :name has been cancelled.',
        'line_without_name' => 'Your invitation has been cancelled.',
        'footer' => 'If you believe this was a mistake, please contact the person who sent you the invitation.',
    ],
];
