<?php

namespace Nuwave\Lighthouse\Exceptions;

use GraphQL\Error\ClientAware;
use Illuminate\Auth\Access\AuthorizationException as IlluminateAuthorizationException;

class AuthorizationException extends IlluminateAuthorizationException implements ClientAware
{
    /**
     * @var string
     */
    const CATEGORY = 'authorization';

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
