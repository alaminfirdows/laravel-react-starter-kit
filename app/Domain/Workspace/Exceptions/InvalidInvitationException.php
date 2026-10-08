<?php

namespace App\Domain\Workspace\Exceptions;

use RuntimeException;

/**
 * The message is safe to show to the user.
 */
class InvalidInvitationException extends RuntimeException
{
    public static function expired(): self
    {
        return new self(__('This invitation has expired. Ask for a new one.'));
    }

    public static function alreadyAccepted(): self
    {
        return new self(__('This invitation has already been accepted.'));
    }

    public static function emailMismatch(): self
    {
        return new self(__('This invitation was sent to a different email address.'));
    }
}
