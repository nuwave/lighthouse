<?php

namespace Nuwave\Lighthouse\Exceptions;

use GraphQL\Error\ClientAware;
use Illuminate\Auth\Access\AuthorizationException as IlluminateAuthorizationException;

class AuthorizationException extends IlluminateAuthorizationException implements ClientAware
{
    public const MESSAGE = 'This action is unauthorized.';

    public function isClientSafe(): bool
    {
        return true;
    }
}
