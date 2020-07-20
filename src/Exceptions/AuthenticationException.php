<?php

namespace Nuwave\Lighthouse\Exceptions;

use Illuminate\Auth\AuthenticationException as IlluminateAuthenticationException;

class AuthenticationException extends IlluminateAuthenticationException implements RendersErrorsExtensions
{
    public const UNAUTHENTICATED = 'Unauthenticated.';

    public function isClientSafe(): bool
    {
        return true;
    }

    public function getCategory(): string
    {
        return 'authentication';
    }

    /**
     * @return array<string, array<string>>
     */
    public function extensionsContent(): array
    {
        return ['guards' => $this->guards];
    }
}
