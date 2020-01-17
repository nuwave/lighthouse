<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;

class SearchDirective extends BaseDirective implements ArgBuilderDirective, DefinedDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Perform a full-text by the given input value.
"""
directive @search(
  """
  Specify a custom index to use for search.
  """
  within: String
) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
SDL;
    }

    /**
     * Apply a scout search to the builder.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
     * @param  mixed  $value
     * @return \Laravel\Scout\Builder
     */
    public function handleBuilder($builder, $value)
    {
        /** @var \Laravel\Scout\Searchable $modelClass */
        $modelClass = get_class($builder->getModel());
        $builder = $modelClass::search($value);

        if ($within = $this->directiveArgValue('within')) {
            $builder->within($within);
        }

        return $builder;
    }
}
