<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Exception;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Laravel\Scout\Builder as ScoutBuilder;
use Laravel\Scout\Searchable;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Utils;

class SearchDirective extends BaseDirective implements ArgBuilderDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Perform a full-text search by the given input value.
"""
directive @search(
  """
  Specify a custom index to use for search.
  """
  within: String
) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    /**
     * Apply a scout search to the builder.
     *
     * @return \Laravel\Scout\Builder
     */
    public function handleBuilder($builder, $value): object
    {
        if ($builder instanceof ScoutBuilder) {
            throw new Exception("Cannot apply {$this->name()} twice on a single query.");
        }

        if ($builder instanceof EloquentBuilder) {
            $model = $builder->getModel();
        } else {
            throw new Exception('Can not get model from builder of class: '.get_class($builder));
        }

        if (! Utils::classUsesTrait($model, Searchable::class)) {
            throw new Exception('Model class ' . get_class($model) . ' does not implement trait ' . Searchable::class);
        }
        // @phpstan-ignore-next-line Can not use traits as types
        /** @var \Illuminate\Database\Eloquent\Model&\Laravel\Scout\Searchable $model */

        $scoutBuilder = $model::search($value);

        if ($within = $this->directiveArgValue('within')) {
            $scoutBuilder->within($within);
        }

        return $scoutBuilder;
    }
}
