<?php

namespace Nuwave\Lighthouse\Exceptions;

use GraphQL\Error\ClientAware;

class AuthorizationException extends \Illuminate\Auth\Access\AuthorizationException implements ClientAware
{

    /**
     * Returns true when exception message is safe to be displayed to a client.
     *
     * @api
     * @return bool
     */
    public function isClientSafe()
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
    public function getCategory()
    {
        return 'authorization';
    }
}
