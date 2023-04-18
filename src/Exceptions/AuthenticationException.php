<?php declare(strict_types=1);

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

    /** @return array{guards: array<string>} */
    public function getExtensions(): array
    {
        return [
            'guards' => $this->guards,
        ];
    }

    public static function fromLaravel(IlluminateAuthenticationException $laravelException): self
    {
        return new static($laravelException->getMessage(), $laravelException->guards());
    }
}
