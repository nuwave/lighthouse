<?php

namespace Nuwave\Lighthouse\Exceptions;

use GraphQL\Error\ClientAware;
use RuntimeException;

/**
 * Thrown when the user has reached the rate limit for a field.
 */
class RateLimitException extends RuntimeException implements ClientAware
{
    public const CATEGORY = 'rate-limit';

    public function __construct(string $fieldReference)
    {
        parent::__construct("Rate limit for {$fieldReference} exceeded. Try again later.");
    }

    public function isClientSafe(): bool
    {
        return true;
    }

    public function getCategory(): string
    {
        return self::CATEGORY;
    }
}
