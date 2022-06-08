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

    public function __construct(string $fieldName)
    {
        parent::__construct(self::buildErrorMessage($fieldName));
    }

    public function isClientSafe(): bool
    {
        return true;
    }

    public function getCategory(): string
    {
        return self::CATEGORY;
    }

    public static function buildErrorMessage(string $fieldName): string
    {
        return "Rate limit for $fieldName exceeded. Try again later.";
    }
}
