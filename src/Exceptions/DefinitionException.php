<?php

namespace Nuwave\Lighthouse\Exceptions;

use Exception;
use GraphQL\Error\ClientAware;

/**
 * Thrown on errors in the schema definition.
 *
 * This signals a developer error, so we do not
 * show this exception to the user.
 */
class DefinitionException extends Exception implements ClientAware
{
    /**
     * Returns true when exception message is safe to be displayed to a client.
     *
     * @api
     * @return bool
     */
    public function isClientSafe(): bool
    {
        return false;
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
        return 'schema';
    }
}
