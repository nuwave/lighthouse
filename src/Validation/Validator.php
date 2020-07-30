<?php

namespace Nuwave\Lighthouse\Validation;

use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;

abstract class Validator
{
    /**
     * The slice of incoming arguments to validate.
     *
     * @var \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet
     */
    protected $args;

    /**
     * Return the validation rules.
     *
     * @return array<string, array<mixed>>
     */
    abstract public function rules(): array;

    /**
     * Return custom messages for failing validations.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Set the slice of args to validate.
     */
    public function setArgs(ArgumentSet $args): void
    {
        $this->args = $args;
    }

    /**
     * Retrieve the value of an argument.
     *
     * @param  string  $key  The key of the argument, may use dot notation to get nested values.
     * @param  mixed|null   $default  Returned in case the argument is not present.
     * @return mixed  The value of the argument or the default.
     */
    protected function arg(string $key, $default = null)
    {
        return Arr::get(
            $this->args->toArray(),
            $key,
            $default
        );
    }
}
