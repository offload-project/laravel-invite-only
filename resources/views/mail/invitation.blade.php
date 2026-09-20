{{--
    The invitation email.

    Publish with `php artisan vendor:publish --tag=invite-only-views` to change
    the layout; the wording lives in the translations, publishable separately
    with `--tag=invite-only-lang`.
--}}
<x-mail::message>
# {{ __('invite-only::notifications.invitation.greeting') }}

@if ($invitableName !== null)
{{ __('invite-only::notifications.invitation.line_with_name', ['name' => $invitableName]) }}
@else
{{ __('invite-only::notifications.invitation.line_without_name') }}
@endif

{{ __('invite-only::notifications.invitation.action_line') }}

<x-mail::button :url="$url">
{{ __('invite-only::notifications.invitation.action_text') }}
</x-mail::button>

{{ __('invite-only::notifications.invitation.footer') }}
</x-mail::message>
