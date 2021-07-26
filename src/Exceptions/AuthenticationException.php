<?php

namespace Nuwave\Lighthouse\Exceptions;

use Illuminate\Auth\AuthenticationException as IlluminateAuthenticationException;

class AuthenticationException extends IlluminateAuthenticationException implements RendersErrorsExtensions
{
    public const MESSAGE = 'Unauthenticated.';

    public function isClientSafe(): bool
    {
        return true;
    }

    public function extensionsContent(): array
    {
        return [
            'guards' => $this->guards,
        ];
    }
}
