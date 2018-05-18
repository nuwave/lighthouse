<?php

namespace Nuwave\Lighthouse\Support\Exceptions;

use GraphQL\Error\Error;

class ValidationError extends Error
{


    /**
     * The validator.
     *
     * @var \Illuminate\Validation\Validator
     */
    protected $validator;

    /**
     * ValidationError constructor.
     *
     * @param \Illuminate\Validation\Validator $validator
     */
    public function __construct(\Illuminate\Validation\Validator $validator)
    {
        parent::__construct("Validation failed");
        $this->setValidator($validator);
    }

    /**
     * Set validator instance.
     *
     * @param mixed $validator
     * @return ValidationError
     */
    public function setValidator($validator)
    {
        $this->validator = $validator;

        return $this;
    }

    /**
     * Get the messages from the validator.
     *
     * @return array
     */
    public function getValidatorMessages()
    {
        return $this->validator ? $this->validator->messages() : [];
    }
}
