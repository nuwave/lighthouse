<?php

namespace Nuwave\Lighthouse\Exceptions;

use Nuwave\Lighthouse\Execution\GraphQLValidator;

class ValidationException extends \Illuminate\Validation\ValidationException implements RendersErrorsExtensions
{
    /**
     * Create a new exception instance.
     *
     * @param GraphQLValidator $validator
     * @param  \Symfony\Component\HttpFoundation\Response $response
     * @param  string $errorBag
     */
    public function __construct(GraphQLValidator $validator, $response = null, $errorBag = 'default')
    {
        \Exception::__construct('Validation failed for the field [' . $validator->getFieldPath() . ']');

        $this->response = $response;
        $this->errorBag = $errorBag;
        $this->validator = $validator;
    }

    public function isClientSafe()
    {
        return true;
    }

    public function getCategory()
    {
        return 'validation';
    }

    public function extensionsContent(): array
    {
        return ['validation' => $this->errors()];
    }
}
