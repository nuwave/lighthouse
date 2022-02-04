<?php

namespace Nuwave\Lighthouse\Exceptions;

use GraphQL\Error\ClientAware;
use Illuminate\Auth\Access\AuthorizationException as IlluminateAuthorizationException;

class AuthorizationException extends IlluminateAuthorizationException implements ClientAware
{
    public const CATEGORY = 'authorization';

    public function isClientSafe(): bool
    {
        return true;
    }

    public function getCategory(): string
    {
        return self::CATEGORY;
    }

    public static function fromLaravel(IlluminateAuthorizationException $laravelException): self
    {
        return new static($laravelException->getMessage(), $laravelException->getCode());
    }
}
