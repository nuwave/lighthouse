<?php

namespace Nuwave\Lighthouse\Scout;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Laravel\Scout\Builder as ScoutBuilder;
use Laravel\Scout\Searchable;
use Nuwave\Lighthouse\Execution\Arguments\Argument;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Schema\Directives\SearchDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Utils;

class ScoutEnhancer
{
    /**
     * @var ArgumentSet
     */
    protected $argumentSet;

    /**
     * @var object
     */
    protected $builder;

    /**
     * Provide the actual search value.
     *
     * @var array<Argument>
     */
    protected $searchArguments = [];

    /**
     * @var array<ScoutBuilderDirective>
     */
    protected $scoutBuilderDirectives = [];

    /**
     * Should not be there when @search is used.
     *
     * @var array<Argument>
     */
    protected $argumentsWithOnlyArgBuilders = [];

    /**
     * Can enhance the scout builders.
     *
     * @var array<Argument>
     */
    protected $argumentsWithScoutBuilderDirectives = [];

    public function __construct(ArgumentSet $argumentSet, object $builder)
    {
        $this->argumentSet = $argumentSet;
        $this->builder = $builder;

        $this->gather($this->argumentSet);
    }

    public function containsSearch(): bool
    {
        return count($this->searchArguments) > 0;
    }

    public function enhance(): ScoutBuilder
    {
        if (count($this->searchArguments) > 1) {
            throw new ScoutException('Found more than 1 argument with @search.');
        }
        $searchArgument = $this->searchArguments[0];

        if (count($this->argumentsWithOnlyArgBuilders) > 0) {
            throw new ScoutException('Found arg builder arguments that do not work with @search');
        }

        if (! $this->builder instanceof EloquentBuilder) {
            throw new ScoutException('Can only get Model from \Illuminate\Database\Eloquent\Builder, got: '.get_class($this->builder));
        }
        $model = $this->builder->getModel();

        if (! Utils::classUsesTrait($model, Searchable::class)) {
            throw new ScoutException('Model class '.get_class($model).' does not implement trait '.Searchable::class);
        }

        // @phpstan-ignore-next-line Can not use traits as types
        /** @var \Illuminate\Database\Eloquent\Model&\Laravel\Scout\Searchable $model */
        $scoutBuilder = $model::search($searchArgument->value);

        /**
         * We know this argument has this directive, because that is how we found it.
         *
         * @var \Nuwave\Lighthouse\Schema\Directives\SearchDirective $searchDirective
         */
        $searchDirective = $searchArgument
            ->directives
            ->first(Utils::instanceofMatcher(SearchDirective::class));

        $searchDirective->search($scoutBuilder);

        foreach ($this->argumentsWithScoutBuilderDirectives as $argument) {
            /** @var \Nuwave\Lighthouse\Scout\ScoutBuilderDirective $scoutBuilderDirective */
            $scoutBuilderDirective = $argument
                ->directives
                ->first(Utils::instanceofMatcher(ScoutBuilderDirective::class));

            $scoutBuilderDirective->handleScoutBuilder($scoutBuilder, $argument->value);
        }

        return $scoutBuilder;
    }

    public function gather(ArgumentSet $argumentSet)
    {
        foreach ($argumentSet->arguments as $argument) {
            $argumentHasSearchDirective = $argument
                ->directives
                ->contains(Utils::instanceofMatcher(SearchDirective::class));

            if ($argumentHasSearchDirective && is_string($argument->value)) {
                $this->searchArguments [] = $argument;
            }

            $argumentHasArgBuilderDirective = $argument
                ->directives
                ->contains(Utils::instanceofMatcher(ArgBuilderDirective::class));

            $argumentHasScoutBuilderDirective = $argument
                ->directives
                ->contains(Utils::instanceofMatcher(ScoutBuilderDirective::class));

            if ($argumentHasArgBuilderDirective && ! $argumentHasScoutBuilderDirective) {
                $this->argumentsWithOnlyArgBuilders [] = $argument;
            }

            if ($argumentHasScoutBuilderDirective) {
                $this->argumentsWithScoutBuilderDirectives [] = $argument;
            }

            Utils::applyEach(
                static function ($value) {
                    if ($value instanceof ArgumentSet) {
                        self::gather($value);
                    }
                },
                $argument->value
            );
        }
    }
}
