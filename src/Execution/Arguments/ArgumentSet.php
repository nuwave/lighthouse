<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution\Arguments;

use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Exceptions\DefinitionException;

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

    /** Check if the ArgumentSet has a value with the given key. */
    public function exists(string $key): bool
    {
        return array_key_exists($key, $this->arguments);
    }

    /**
     * Add a value at the dot-separated path.
     *
     * Works just like @see \Illuminate\Support\Arr::add(), but a path segment
     * of `*` injects the value into every element of the list found at that
     * position instead of a single key.
     */
    public function addValue(string $path, mixed $value): self
    {
        $this->addValueAtKeys(explode('.', $path), $value);

        return $this;
    }

    /** @param  non-empty-array<int, string>  $keys */
    private function addValueAtKeys(array $keys, mixed $value): void
    {
        $key = array_shift($keys);

        if ($keys === []) {
            $argument = new Argument();
            $argument->value = $value;
            $this->arguments[$key] = $argument;

            return;
        }

        $throughWildcard = $keys[0] === '*';

        // The branch leading up to a `*` might not have been given by the client
        // at all, e.g. an optional nested input that was omitted. In that case,
        // there is nothing to inject into, so we just do nothing.
        if ($throughWildcard && ! isset($this->arguments[$key])) {
            return;
        }

        // If the key doesn't exist at this depth, we will create an empty ArgumentSet
        // to hold the next value, allowing us to create the ArgumentSet to hold a final
        // value at the correct depth. Then we'll keep digging into the ArgumentSet.
        if (! isset($this->arguments[$key])) {
            $argument = new Argument();
            $argument->value = new self();
            $this->arguments[$key] = $argument;
        }

        $argument = $this->arguments[$key];

        if (! $throughWildcard) {
            $argument->value->addValueAtKeys($keys, $value);

            return;
        }

        array_shift($keys);
        if ($keys === []) {
            throw new DefinitionException("Can not use `*` as the final path segment of the `name` argument of `@inject`, a field to inject into must follow, got: {$key}.*");
        }

        if (! is_array($argument->value)) {
            throw new DefinitionException("Expected the value at `{$key}` to be a list because of the `*` wildcard used in the `name` argument of `@inject`, got: " . get_debug_type($argument->value) . '.');
        }

        foreach ($argument->value as $element) {
            if (! $element instanceof self) {
                throw new DefinitionException("Expected the elements of the list at `{$key}` to be inputs because of the `*` wildcard used in the `name` argument of `@inject`, got: " . get_debug_type($element) . '.');
            }

            $element->addValueAtKeys($keys, $value);
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
}
