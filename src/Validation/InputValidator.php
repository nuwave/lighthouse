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
     *
     * @return array
     */
    abstract public function rules(): array;

    /**
     * Return custom messages for failing validations.
     *
     * @return array
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Set the slice of input to validate.
     *
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet  $input
     * @return void
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
     * @param  string  $key
     * @param  mixed|null   $default
     *
     * @return mixed
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
