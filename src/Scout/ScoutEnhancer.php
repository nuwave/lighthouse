<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Scout;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Laravel\Scout\Builder as ScoutBuilder;
use Laravel\Scout\Searchable;
use Nuwave\Lighthouse\Execution\Arguments\Argument;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Utils;

/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 */
class ScoutEnhancer
{
    /**
     * Provide the actual search value.
     *
     * @var array<Argument>
     */
    protected array $searchArguments = [];

    /**
     * Should not be there when @search is used.
     *
     * @var array<Argument>
     */
    protected array $argumentsWithOnlyArgBuilders = [];

    /**
     * Can enhance the scout builders.
     *
     * @var array<Argument>
     */
    protected array $argumentsWithScoutBuilderDirectives = [];

    public function __construct(
        protected ArgumentSet $argumentSet,
        /**
         * @var \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<TModel>|\Illuminate\Database\Eloquent\Relations\Relation<TModel>|\Laravel\Scout\Builder $builder
         */
        protected QueryBuilder|EloquentBuilder|Relation|ScoutBuilder $builder,
    ) {
        $this->gather($this->argumentSet);
    }

    public function hasSearchArguments(): bool
    {
        return $this->searchArguments !== [];
    }

    public function canEnhanceBuilder(): bool
    {
        return $this->hasSearchArguments()
            || $this->builder instanceof ScoutBuilder;
    }

    public function wouldEnhanceBuilder(): bool
    {
        return $this->hasSearchArguments();
    }

    /** @param  (callable(\Nuwave\Lighthouse\Scout\ScoutBuilderDirective): bool)|null  $directiveFilter */
    public function enhanceBuilder(?callable $directiveFilter = null): ScoutBuilder
    {
        $scoutBuilder = $this->builder instanceof ScoutBuilder
            ? $this->builder
            : $this->enhanceEloquentBuilder();

        foreach ($this->argumentsWithScoutBuilderDirectives as $argument) {
            foreach ($argument->directives as $directive) {
                if (! ($directive instanceof ScoutBuilderDirective)) {
                    continue;
                }

                if ($directiveFilter !== null && ! $directiveFilter($directive)) {
                    continue;
                }

                $directive->handleScoutBuilder($scoutBuilder, $argument->toPlain());
            }
        }

        return $scoutBuilder;
    }

    protected function gather(ArgumentSet $argumentSet): void
    {
        foreach ($argumentSet->arguments as $argument) {
            $argumentHasSearchDirective = $argument
                ->directives
                ->contains(Utils::instanceofMatcher(SearchDirective::class));

            if ($argumentHasSearchDirective && is_string($argument->value)) {
                $this->searchArguments[] = $argument;
            }

            $argumentHasArgBuilderDirective = $argument
                ->directives
                ->contains(Utils::instanceofMatcher(ArgBuilderDirective::class));

            $argumentHasScoutBuilderDirective = $argument
                ->directives
                ->contains(Utils::instanceofMatcher(ScoutBuilderDirective::class));

            if ($argumentHasArgBuilderDirective && ! $argumentHasScoutBuilderDirective) {
                $this->argumentsWithOnlyArgBuilders[] = $argument;
            }

            if ($argumentHasScoutBuilderDirective) {
                $this->argumentsWithScoutBuilderDirectives[] = $argument;
            }

            Utils::applyEach(
                function ($value): void {
                    if ($value instanceof ArgumentSet) {
                        $this->gather($value);
                    }
                },
                $argument->value,
            );
        }
    }

    protected function enhanceEloquentBuilder(): ScoutBuilder
    {
        if (count($this->searchArguments) > 1) {
            throw new ScoutException('Found more than 1 argument with @search.');
        }

        $searchArgument = $this->searchArguments[0];

        if ($this->argumentsWithOnlyArgBuilders !== []) {
            throw new ScoutException('Found arg builder arguments that do not work with @search.');
        }

        if (! $this->builder instanceof EloquentBuilder) {
            $eloquentBuilderClass = EloquentBuilder::class;
            $thisBuilderClass = $this->builder::class;
            throw new ScoutException("Can only get Model from {$eloquentBuilderClass}, got: {$thisBuilderClass}.");
        }

        $model = $this->builder->getModel();

        $searchableTraitClass = Searchable::class;
        if (! Utils::classUsesTrait($model, $searchableTraitClass)) {
            $modelClass = $model::class;
            throw new ScoutException("Model class {$modelClass} does not implement trait {$searchableTraitClass}.");
        }

        // @phpstan-ignore-next-line Can not use traits as types
        /** @var \Illuminate\Database\Eloquent\Model&\Laravel\Scout\Searchable $model */
        $scoutBuilder = $model::search($searchArgument->toPlain());

        $searchDirective = $searchArgument
            ->directives
            ->first(Utils::instanceofMatcher(SearchDirective::class));
        assert($searchDirective instanceof SearchDirective);

        $searchDirective->search($scoutBuilder);

        return $scoutBuilder;
    }
}
