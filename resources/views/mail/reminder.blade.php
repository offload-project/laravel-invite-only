{{-- The reminder for an invitation nobody has taken up yet. --}}
<x-mail::message>
# {{ __('invite-only::notifications.reminder.greeting') }}

@if ($invitableName !== null)
{{ __('invite-only::notifications.reminder.line_with_name', ['name' => $invitableName]) }}
@else
{{ __('invite-only::notifications.reminder.line_without_name') }}
@endif

@if ($expiresAt !== null)
{{ __('invite-only::notifications.reminder.expires_line', ['date' => $expiresAt]) }}
@endif

<x-mail::button :url="$url">
{{ __('invite-only::notifications.reminder.action_text') }}
</x-mail::button>

{{ __('invite-only::notifications.reminder.footer') }}
</x-mail::message>
