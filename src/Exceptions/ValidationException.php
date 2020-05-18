<?php

namespace Nuwave\Lighthouse\Exceptions;

class ValidationException extends \Illuminate\Validation\ValidationException implements RendersErrorsExtensions
{
    const CATEGORY = 'validation';

    public function isClientSafe()
    {
        return true;
    }

    public function getCategory()
    {
        return self::CATEGORY;
    }

    public function extensionsContent(): array
    {
        return [self::CATEGORY => $this->errors()];
    }
}
