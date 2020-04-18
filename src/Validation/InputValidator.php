<?php

namespace Nuwave\Lighthouse\Validation;

use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;

abstract class InputValidator
{
    /**
     * The slice of incoming arguments to validate.
     *
     * @var \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet
     */
    protected $input;

    /**
     * Return the validation rules for the input.
     */
    abstract public function rules(): array;

    /**
     * Return custom messages for failing validations.
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Set the slice of input to validate.
     */
    public function setInput(ArgumentSet $input): void
    {
        $this->input = $input;
    }

    /**
     * Retrieve the value of an input.
     *
     * Allows getting nested values through dot notation.
     *
     * @param  mixed|null   $default
     */
    protected function input(string $key, $default = null)
    {
        return Arr::get(
            $this->input->toArray(),
            $key,
            $default
        );
    }
}
