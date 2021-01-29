<?php


namespace Nuwave\Lighthouse\Exceptions;

use GraphQL\Error\ClientAware;
use RuntimeException;

/**
 * Thrown when the user have reached request rate limit.
 */
class RateLimitException extends RuntimeException implements ClientAware
{
    public const MESSAGE = 'Rate limit exceeded. Please try later.';
    public const CATEGORY = 'rate-limit';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }

    /**
     * Returns true when exception message is safe to be displayed to a client.
     *
     * @api
     * @return bool
     */
    public function isClientSafe(): bool
    {
        return true;
    }

    /**
     * Returns string describing a category of the error.
     *
     * Value "graphql" is reserved for errors produced by query parsing or validation, do not use it.
     *
     * @api
     * @return string
     */
    public function getCategory(): string
    {
        return self::CATEGORY;
    }
}
