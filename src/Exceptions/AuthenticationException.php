<?php

namespace Nuwave\Lighthouse\Exceptions;

use GraphQL\Error\ClientAware;
use GraphQL\Error\ProvidesExtensions;
use Illuminate\Auth\AuthenticationException as IlluminateAuthenticationException;

class AuthenticationException extends IlluminateAuthenticationException implements ClientAware, ProvidesExtensions
{
    public const MESSAGE = 'Unauthenticated.';

    public function isClientSafe(): bool
    {
        return true;
    }

    public function getExtensions(): array
    {
        return [
            'guards' => $this->guards,
        ];
    }
}
