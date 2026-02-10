<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Validation;

use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Support\Contracts\ArgumentSetValidation;

abstract class Validator implements ArgumentSetValidation
{
    /** The slice of incoming arguments to validate. */
    protected ArgumentSet $args;

    public function messages(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [];
    }

    /** Set the slice of args to validate. */
    public function setArgs(ArgumentSet $args): void
    {
        $this->args = $args;
    }

    /**
     * Retrieve the value of an argument or the default.
     *
     * @param  string  $key  the key of the argument, may use dot notation to get nested values
     * @param  mixed  $default returned in case the argument is not present
     */
    protected function arg(string $key, mixed $default = null): mixed
    {
        return Arr::get(
            $this->args->toArray(),
            $key,
            $default,
        );
    }
}
