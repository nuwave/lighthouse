<?php

namespace Nuwave\Lighthouse\Exceptions;

use Illuminate\Validation\ValidationException as IlluminateValidationException;

class ValidationException extends IlluminateValidationException implements RendersErrorsExtensions
{
    /**
     * Returns true when exception message is safe to be displayed to a client.
     *
     * @api
     * @return bool
     */
    public function isClientSafe(): bool
    {
        return true;
    }

    /**
     * Returns string describing a category of the error.
     *
     * @api
     * @return string
     */
    public function getCategory(): string
    {
        return 'validation';
    }

    /**
     * Return the content that is put in the "extensions" part
     * of the returned error.
     *
     * @return array
     */
    public function extensionsContent(): array
    {
        return [$this->getCategory() => $this->errors()];
    }
}
