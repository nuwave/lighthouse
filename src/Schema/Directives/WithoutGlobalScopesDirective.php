<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirectiveForArray;

final class WithoutGlobalScopesDirective extends BaseDirective implements ArgBuilderDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Removes any number of global scopes from the query builder.

The scopes will only be removed if the client-given value of the argument is truthy.

  """
  The names of the global scopes to omit.
  """
names: [String!]
) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    public function handleBuilder(QueryBuilder|EloquentBuilder|Relation $builder, mixed $value): QueryBuilder|EloquentBuilder|Relation
    {
        if (!$value) {
            return $builder;
        }
        $scopes = $this->directiveArgValue('names', $this->nodeName());

        try {
            return $builder->withoutGlobalScopes($scopes);

        } catch (\BadMethodCallException $badMethodCallException) {
            throw new DefinitionException(
                "{$badMethodCallException->getMessage()} in @{$this->name()} directive on {$this->nodeName()} argument.",
                $badMethodCallException->getCode(),
                $badMethodCallException->getPrevious(),
            );
        }
    }
}
