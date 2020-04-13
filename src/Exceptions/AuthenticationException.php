<?php

namespace Nuwave\Lighthouse\Exceptions;

use Illuminate\Auth\AuthenticationException as IlluminateAuthenticationException;

class AuthenticationException extends IlluminateAuthenticationException implements RendersErrorsExtensions
{
    public const UNAUTHENTICATED = 'Unauthenticated.';

    /**
     * Returns true when exception message is safe to be displayed to a client.
     *
     * @api
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
     */
    public function getCategory(): string
    {
        return 'authentication';
    }

    /**
     * Return the content that is put in the "extensions" part
     * of the returned error.
     */
    public function extensionsContent(): array
    {
        return ['guards' => $this->guards];
    }
}
