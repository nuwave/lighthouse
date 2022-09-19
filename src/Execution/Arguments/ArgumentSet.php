<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use Closure;
use Nuwave\Lighthouse\Scout\ScoutEnhancer;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldBuilderDirective;
use Nuwave\Lighthouse\Support\Utils;

class ArgumentSet
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
     * Apply ArgBuilderDirectives and scopes to the builder.
     *
     * @template TBuilder of \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation|\Laravel\Scout\Builder
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation|\Laravel\Scout\Builder  $builder
     *
     * @phpstan-param  TBuilder  $builder
     *
     * @param  array<string>  $scopes
     *
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation|\Laravel\Scout\Builder
     *
     * @phpstan-return TBuilder
     */
    public function enhanceBuilder(object $builder, array $scopes, Closure $directiveFilter = null): object
    {
        $scoutEnhancer = new ScoutEnhancer($this, $builder);
        if ($scoutEnhancer->canEnhanceBuilder()) {
            return $scoutEnhancer->enhanceBuilder();
        }

        self::applyArgBuilderDirectives($this, $builder, $directiveFilter);
        self::applyFieldBuilderDirectives($this, $builder);

        foreach ($scopes as $scope) {
            $builder->{$scope}($this->toArray());
        }

        return $builder;
    }

    /**
     * Recursively apply the ArgBuilderDirectives onto the builder.
     *
     * TODO get rid of the reference passing in here. The issue is that @search makes a new builder instance,
     * but we must special case that in some way anyhow, as only eq filters can be added on top of search.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
     * @param  (\Closure(\Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective): bool)|null  $directiveFilter
     */
    protected static function applyArgBuilderDirectives(self $argumentSet, object &$builder, Closure $directiveFilter = null): void
    {
        $unboxBenSampoEnumEnumInstances = config('lighthouse.unbox_bensampo_enum_enum_instances');

        foreach ($argumentSet->arguments as $argument) {
            $value = $argument->toPlain();

            // TODO remove in v6, Laravel automagically calls the Enum's __toString() method
            // Unbox Enum values to ensure their underlying value is used for queries
            if ($unboxBenSampoEnumEnumInstances && is_a($value, '\BenSampo\Enum\Enum')) {
                $value = $value->value;
            }

            $filteredDirectives = $argument
                ->directives
                ->filter(Utils::instanceofMatcher(ArgBuilderDirective::class));

            if (null !== $directiveFilter) {
                $filteredDirectives = $filteredDirectives->filter($directiveFilter);
            }

            $filteredDirectives->each(static function (ArgBuilderDirective $argBuilderDirective) use (&$builder, $value): void {
                $builder = $argBuilderDirective->handleBuilder($builder, $value);
            });

            Utils::applyEach(
                static function ($value) use (&$builder, $directiveFilter) {
                    if ($value instanceof self) {
                        self::applyArgBuilderDirectives($value, $builder, $directiveFilter);
                    }
                },
                $argument->value
            );
        }
    }

    /**
     * Apply the FieldBuilderDirectives onto the builder.
     *
     * TODO get rid of the reference passing in here. The issue is that @search makes a new builder instance,
     * but we must special case that in some way anyhow, as only eq filters can be added on top of search.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
     */
    protected static function applyFieldBuilderDirectives(self $argumentSet, object &$builder): void
    {
        $argumentSet->directives
            ->filter(Utils::instanceofMatcher(FieldBuilderDirective::class))
            ->each(static function (FieldBuilderDirective $fieldBuilderDirective) use (&$builder): void {
                $builder = $fieldBuilderDirective->handleFieldBuilder($builder);
            });
    }

    /**
     * Add a value at the dot-separated path.
     *
     * Works just like @see \Illuminate\Support\Arr::add().
     *
     * @param  mixed  $value  any value to inject
     */
    public function addValue(string $path, $value): self
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
