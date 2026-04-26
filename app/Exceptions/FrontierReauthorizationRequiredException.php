<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when the Frontier refresh token has expired or been revoked,
 * meaning the user must complete the OAuth flow again.
 * Refresh tokens expire 25 days after initial authorization.
 */
class FrontierReauthorizationRequiredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Frontier reauthorization required.');
    }
}
