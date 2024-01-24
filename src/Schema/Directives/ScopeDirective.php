<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;

class ScopeDirective extends BaseDirective implements ArgBuilderDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Adds a scope to the query builder.

The scope method will receive the client-given value of the argument as the second parameter.
This also works with custom query builders, it simply calls its methods with the argument value.
"""
directive @scope(
  """
  The name of the scope or method on the custom query builder.
  Defaults to the name of the argument or input field.
  """
  name: String
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    public function handleBuilder(QueryBuilder|EloquentBuilder|Relation $builder, $value): QueryBuilder|EloquentBuilder|Relation
    {
        $scope = $this->directiveArgValue('name', $this->nodeName());

        try {
            return $builder->{$scope}($value);
        } catch (\BadMethodCallException $badMethodCallException) {
            throw new DefinitionException(
                "{$badMethodCallException->getMessage()} in @{$this->name()} directive on {$this->nodeName()} argument.",
                $badMethodCallException->getCode(),
                $badMethodCallException->getPrevious(),
            );
        }
    }
}
