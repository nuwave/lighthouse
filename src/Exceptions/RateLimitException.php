<?php

namespace Nuwave\Lighthouse\Exceptions;

use GraphQL\Error\ClientAware;
use RuntimeException;

/**
 * Thrown when the user has reached the rate limit for a field.
 */
class RateLimitException extends RuntimeException implements ClientAware
{
    public const MESSAGE = 'Rate limit for %s exceeded. Try again later.';
    public const CATEGORY = 'rate-limit';

    public function __construct(string $queryPath)
    {
        parent::__construct(self::buildErrorMessage($queryPath));
    }

    public function isClientSafe(): bool
    {
        return true;
    }

    public function getCategory(): string
    {
        return self::CATEGORY;
    }

    public static function buildErrorMessage(string $queryPath): string
    {
        return sprintf(self::MESSAGE, $queryPath);
    }
}
