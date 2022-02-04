<?php

namespace Nuwave\Lighthouse\Exceptions;

use Illuminate\Auth\AuthenticationException as IlluminateAuthenticationException;

class AuthenticationException extends IlluminateAuthenticationException implements RendersErrorsExtensions
{
    public const MESSAGE = 'Unauthenticated.';
    public const CATEGORY = 'authentication';

    public function isClientSafe(): bool
    {
        return true;
    }

    public function getCategory(): string
    {
        return self::CATEGORY;
    }

    /**
     * @return array<string, array<string>>
     */
    public function extensionsContent(): array
    {
        return ['guards' => $this->guards];
    }

    public static function fromLaravel(IlluminateAuthenticationException $laravelException): self
    {
        return new static($laravelException->getMessage(), $laravelException->guards());
    }
}
