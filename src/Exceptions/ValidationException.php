<?php

namespace Nuwave\Lighthouse\Exceptions;

class ValidationException extends \Illuminate\Validation\ValidationException implements RendersErrorsExtensions
{
    const CATEGORY = 'validation';

    /**
     * Returns true when exception message is safe to be displayed to a client.
     *
     * @return bool
     */
    public function isClientSafe()
    {
        return true;
    }

    /**
     * Returns string describing a category of the error.
     *
     * @return string
     */
    public function getCategory()
    {
        return self::CATEGORY;
    }

    /**
     * Return the content that is put in the "extensions" part
     * of the returned error.
     */
    public function extensionsContent(): array
    {
        return [self::CATEGORY => $this->errors()];
    }
}
