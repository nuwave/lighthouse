<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution\Arguments;

use Illuminate\Support\Collection;

class ArgumentSet
{
    /**
     * An associative array from argument names to arguments.
     *
     * @var array<string, \Nuwave\Lighthouse\Execution\Arguments\Argument>
     */
    public array $arguments = [];

    /**
     * An associative array of arguments that were not given.
     *
     * @var array<string, \Nuwave\Lighthouse\Execution\Arguments\Argument>
     */
    public array $undefined = [];

    /**
     * A list of directives.
     *
     * This may be coming from
     * - the field the arguments are a part of
     * - the parent argument when in a tree of nested inputs.
     *
     * @var \Illuminate\Support\Collection<int, \Nuwave\Lighthouse\Support\Contracts\Directive>
     */
    public Collection $directives;

    /**
     * Get a plain array representation of this ArgumentSet.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $plainArguments = [];

        foreach ($this->arguments as $name => $argument) {
            $plainArguments[$name] = $argument->toPlain();
        }

        return $plainArguments;
    }

    /** Check if the ArgumentSet has a non-null value with the given key. */
    public function has(string $key): bool
    {
        $argument = $this->arguments[$key] ?? null;

        if (! $argument instanceof Argument) {
            return false;
        }

        return isset($argument->value);
    }

    /**
     * Add a value at the dot-separated path.
     *
     * Works just like @see \Illuminate\Support\Arr::add().
     */
    public function addValue(string $path, mixed $value): self
    {
        $argumentSet = $this;
        $keys = explode('.', $path);

        while (count($keys) > 1) {
            $key = array_shift($keys);

            // If the key doesn't exist at this depth, we will just create an empty ArgumentSet
            // to hold the next value, allowing us to create the ArgumentSet to hold a final
            // value at the correct depth. Then we'll keep digging into the ArgumentSet.
            if (! isset($argumentSet->arguments[$key])) {
                $argument = new Argument();
                $argument->value = new self();
                $argumentSet->arguments[$key] = $argument;
            }

            $argumentSet = $argumentSet->arguments[$key]->value;
        }

        $argument = new Argument();
        $argument->value = $value;
        $argumentSet->arguments[array_shift($keys)] = $argument;

        return $this;
    }

    /**
     * The contained arguments, including all that were not passed.
     *
     * @return array<string, \Nuwave\Lighthouse\Execution\Arguments\Argument>
     */
    public function argumentsWithUndefined(): array
    {
        return array_merge($this->arguments, $this->undefined);
    }
}
