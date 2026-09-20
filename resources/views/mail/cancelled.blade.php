{{-- Sent when an invitation is withdrawn before it is accepted. There is
     nothing to click, so this one carries no button. --}}
<x-mail::message>
# {{ __('invite-only::notifications.cancelled.greeting') }}

@if ($invitableName !== null)
{{ __('invite-only::notifications.cancelled.line_with_name', ['name' => $invitableName]) }}
@else
{{ __('invite-only::notifications.cancelled.line_without_name') }}
@endif

{{ __('invite-only::notifications.cancelled.footer') }}
</x-mail::message>
