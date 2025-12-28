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
            return $this->redirectWithError('Invalid invitation link.');
        }

        if ($invitation->isExpired()) {
            return $this->redirectExpired('This invitation has expired.');
        }

        if ($invitation->isAccepted()) {
            return $this->redirectWithSuccess('This invitation has already been accepted.');
        }

        if ($invitation->isCancelled()) {
            return $this->redirectWithError('This invitation has been cancelled.');
        }

        if ($invitation->isDeclined()) {
            return $this->redirectWithError('This invitation has been declined.');
        }

        return redirect()->to($invitation->getAcceptUrl());
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        try {
            $user = $request->user();
            $invitation = InviteOnly::accept($token, $user);

            return $this->redirectAccepted('You have successfully accepted the invitation.');
        } catch (InvalidInvitationException $e) {
            return $this->redirectWithError($e->getMessage());
        } catch (InvitationAlreadyAcceptedException) {
            return $this->redirectWithSuccess('This invitation has already been accepted.');
        } catch (InvitationExpiredException) {
            return $this->redirectExpired('This invitation has expired.');
        }
    }

    public function decline(string $token): RedirectResponse
    {
        try {
            InviteOnly::decline($token);

            return $this->redirectDeclined('You have declined the invitation.');
        } catch (InvalidInvitationException $e) {
            return $this->redirectWithError($e->getMessage());
        }
    }

    private function redirectAccepted(string $message): RedirectResponse
    {
        $url = config('invite-only.redirect.accepted', '/');

        return redirect()->to($url)->with([
            'invitation_status' => 'accepted',
            'invitation_message' => $message,
        ]);
    }

    private function redirectDeclined(string $message): RedirectResponse
    {
        $url = config('invite-only.redirect.declined', '/');

        return redirect()->to($url)->with([
            'invitation_status' => 'declined',
            'invitation_message' => $message,
        ]);
    }

    private function redirectExpired(string $message): RedirectResponse
    {
        $url = config('invite-only.redirect.expired', '/');

        return redirect()->to($url)->with([
            'invitation_status' => 'expired',
            'invitation_message' => $message,
        ]);
    }

    private function redirectWithError(string $message): RedirectResponse
    {
        return redirect()->to('/')->with([
            'invitation_status' => 'error',
            'invitation_message' => $message,
        ]);
    }

    private function redirectWithSuccess(string $message): RedirectResponse
    {
        return redirect()->to('/')->with([
            'invitation_status' => 'success',
            'invitation_message' => $message,
        ]);
    }
}
