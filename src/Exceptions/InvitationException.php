<?php

declare(strict_types=1);

namespace OffloadProject\InviteOnly\Exceptions;

use Exception;
use OffloadProject\InviteOnly\Models\Invitation;

/**
 * Base exception for all invitation-related errors.
 *
 * Provides structured error information to help developers quickly
 * understand and resolve issues.
 */
abstract class InvitationException extends Exception
{
    /**
     * @param  string  $message  Human-readable description of what happened
     * @param  string  $errorCode  Machine-readable error code for programmatic handling
     * @param  string  $resolution  Actionable guidance on how to fix the issue
     * @param  Invitation|null  $invitation  The invitation involved, if available
     */
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly string $resolution,
        public readonly ?Invitation $invitation = null,
    ) {
        parent::__construct($message);
    }

    /**
     * Get a structured array representation of the error.
     *
     * Useful for API responses or logging.
     *
     * @return array{message: string, code: string, resolution: string, invitation_id: int|null}
     */
    final public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
            'resolution' => $this->resolution,
            'invitation_id' => $this->invitation?->id,
        ];
    }

    /**
     * Get the documentation URL for this error.
     */
    final public function getDocumentationUrl(): string
    {
        return "https://github.com/offload-project/laravel-invite-only#troubleshooting-{$this->errorCode}";
    }
}
