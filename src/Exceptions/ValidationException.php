<?php

namespace Nuwave\Lighthouse\Exceptions;

class ValidationException extends \Illuminate\Validation\ValidationException implements RendersErrorsExtensions
{
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
        return ['validation' => $this->errors()];
    }
}
