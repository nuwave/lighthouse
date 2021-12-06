<?php

namespace Nuwave\Lighthouse\Validation;

use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Support\Contracts\ArgumentSetValidation;

abstract class Validator implements ArgumentSetValidation
{
    /**
     * The slice of incoming arguments to validate.
     *
     * @var \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet
     */
    protected $args;

    public function messages(): array
    {
        return [];
    }

    public function attributes(): array
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
     * @param  string  $key  the key of the argument, may use dot notation to get nested values
     * @param  mixed|null  $default  returned in case the argument is not present
     *
     * @return mixed the value of the argument or the default
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
