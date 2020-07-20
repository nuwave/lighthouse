<?php

namespace Nuwave\Lighthouse\Exceptions;

use Exception;
use Illuminate\Contracts\Validation\Validator;

class ValidationException extends Exception implements RendersErrorsExtensions
{
    const CATEGORY = 'validation';

    /**
     * @var \Illuminate\Contracts\Validation\Validator
     */
    protected $validator;

    public function __construct(string $message, Validator $validator)
    {
        parent::__construct($message);

        $this->validator = $validator;
    }

    public function isClientSafe(): bool
    {
        return true;
    }

    public function getCategory(): string
    {
        return self::CATEGORY;
    }

    public function extensionsContent(): array
    {
        return [
            'validation' => $this->validator->errors()->messages(),
        ];
    }
}
