<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

class ArgumentSet implements \ArrayAccess
{
    /**
     * An associative array from argument names to arguments.
     *
     * @var array<string, \Nuwave\Lighthouse\Execution\Arguments\Argument>
     */
    public $arguments = [];

    /**
     * An associative array of arguments that were not given.
     *
     * @var array<string, \Nuwave\Lighthouse\Execution\Arguments\Argument>
     */
    public $undefined = [];

    /**
     * A list of directives.
     *
     * This may be coming from
     * - the field the arguments are a part of
     * - the parent argument when in a tree of nested inputs.
     *
     * @var \Illuminate\Support\Collection<\Nuwave\Lighthouse\Support\Contracts\Directive>
     */
    public $directives;

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

    /**
     * Check if the ArgumentSet has a non-null value with the given key.
     */
    public function has(string $key): bool
    {
        $argument = $this->arguments[$key] ?? null;

        if (! $argument instanceof Argument) {
            return false;
        }

        return null !== $argument->value;
    }

    /**
     * Add a value at the dot-separated path.
     * Asterisks may be used to indicate wildcards.
     *
     * @param  mixed  $value  any value to inject
     */
    public function addValue(string $path, mixed $value): self
    {
        $argumentSet = $this;

        data_set($argumentSet, $path, $value);
        self::removeEmptyArrays($argumentSet);

        return $this;
    }

    /**
     * Remove all arguments which is an empty array.
     */
    private static function removeEmptyArrays(ArgumentSet $argumentSet): void
    {
        foreach ($argumentSet->arguments as $name => $argument) {
            if (is_array($argument->value)) {
                foreach ($argument->value as $value) {
                    self::removeEmptyArrays($value);
                }
            }

            if (is_array($argument->value) && empty($argument->value)) {
                unset($argumentSet[$name]);
            }
        }
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

    public function offsetExists(mixed $offset): bool
    {
        $argumentSet = $this;

        return isset($argumentSet->arguments[$offset]);
    }

    public function offsetGet(mixed $offset): Argument
    {
        $argumentSet = $this;

        return $argumentSet->arguments[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $argumentSet = $this;

        $argument = new Argument();
        $argument->value = $value ?: new self();
        $argumentSet->arguments[(string) $offset] = $argument;
    }

    public function offsetUnset(mixed $offset): void
    {
        $argumentSet = $this;

        unset($argumentSet->arguments[$offset]);
    }
}
