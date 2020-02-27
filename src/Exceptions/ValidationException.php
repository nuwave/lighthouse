<?php

namespace Nuwave\Lighthouse\Exceptions;

use Exception;
use Illuminate\Contracts\Validation\Validator;

class ValidationException extends Exception implements RendersErrorsExtensions
{
    /**
     * @var \Illuminate\Contracts\Validation\Validator
     */
    protected $validator;

    public function __construct(string $message, Validator $validator)
    {
        parent::__construct($message);

        $this->validator = $validator;
    }

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
        return [
            'validation' => $this->validator->errors()->messages(),
        ];
    }
}
