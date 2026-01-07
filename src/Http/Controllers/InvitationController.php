<?php

declare(strict_types=1);

namespace OffloadProject\InviteOnly\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use OffloadProject\InviteOnly\Exceptions\InvalidInvitationException;
use OffloadProject\InviteOnly\Exceptions\InvitationAlreadyAcceptedException;
use OffloadProject\InviteOnly\Exceptions\InvitationExpiredException;
use OffloadProject\InviteOnly\Facades\InviteOnly;

final class InvitationController extends Controller
{
    public function show(string $token): RedirectResponse
    {
        $invitation = InviteOnly::find($token);

        if ($invitation === null) {
            return $this->redirectWithStatus('error', 'Invalid invitation link.');
        }

        if ($invitation->isExpired()) {
            return $this->redirectWithStatus('expired', 'This invitation has expired.', 'expired');
        }

        if ($invitation->isAccepted()) {
            return $this->redirectWithStatus('success', 'This invitation has already been accepted.');
        }

        if ($invitation->isCancelled()) {
            return $this->redirectWithStatus('error', 'This invitation has been cancelled.');
        }

        if ($invitation->isDeclined()) {
            return $this->redirectWithStatus('error', 'This invitation has been declined.');
        }

        return redirect()->to($invitation->getAcceptUrl());
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        try {
            $user = $request->user();
            $invitation = InviteOnly::accept($token, $user);

            return $this->redirectWithStatus('accepted', 'You have successfully accepted the invitation.', 'accepted');
        } catch (InvalidInvitationException $e) {
            return $this->redirectWithStatus('error', $e->getMessage());
        } catch (InvitationAlreadyAcceptedException) {
            return $this->redirectWithStatus('success', 'This invitation has already been accepted.');
        } catch (InvitationExpiredException) {
            return $this->redirectWithStatus('expired', 'This invitation has expired.', 'expired');
        }
    }

    public function decline(string $token): RedirectResponse
    {
        try {
            InviteOnly::decline($token);

            return $this->redirectWithStatus('declined', 'You have declined the invitation.', 'declined');
        } catch (InvalidInvitationException $e) {
            return $this->redirectWithStatus('error', $e->getMessage());
        }
    }

    /**
     * Redirect with invitation status and message.
     *
     * @param  string  $status  The status key (accepted, declined, expired, error, success)
     * @param  string  $message  The message to flash
     * @param  string|null  $configKey  Optional config key for redirect URL (accepted, declined, expired, error)
     */
    private function redirectWithStatus(string $status, string $message, ?string $configKey = null): RedirectResponse
    {
        $url = $configKey !== null
            ? config("invite-only.redirect.{$configKey}", '/')
            : '/';

        return redirect()->to($url)->with([
            'invitation_status' => $status,
            'invitation_message' => $message,
        ]);
    }
}
