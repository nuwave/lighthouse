<?php

namespace Nuwave\Lighthouse\Exceptions;

use GraphQL\Error\ClientAware;
use RuntimeException;

/**
 * Thrown when the user has reached the rate limit for a field.
 */
class RateLimitException extends RuntimeException implements ClientAware
{
    public const MESSAGE = 'Rate limit exceeded. Please try later.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }

    public function isClientSafe(): bool
    {
        return true;
    }
}
