{{-- Sent to whoever issued the invitation once it is taken up. --}}
<x-mail::message>
# {{ __('invite-only::notifications.accepted.greeting') }}

@if ($invitableName !== null)
{{ __('invite-only::notifications.accepted.line_with_name', ['email' => $acceptedByEmail, 'name' => $invitableName]) }}
@else
{{ __('invite-only::notifications.accepted.line_without_name', ['email' => $acceptedByEmail]) }}
@endif

{{ __('invite-only::notifications.accepted.team_line') }}

<x-mail::button :url="$url">
{{ __('invite-only::notifications.accepted.action_text') }}
</x-mail::button>
</x-mail::message>
