<?php

namespace App\Domain\Workspace\Exceptions;

use RuntimeException;

class WorkspaceNotSetException extends RuntimeException
{
    public static function make(): self
    {
        return new self('No workspace is set for the current context.');
    }
}
