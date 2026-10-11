<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;

final class WithoutGlobalScopesDirective extends BaseDirective implements ArgBuilderDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Omit any number of global scopes from the query builder.

This directive should be used on arguments of type `Boolean`.
The scopes will be removed only if `true` is passed by the client.
"""
directive @withoutGlobalScopes(
  """
  The names of the global scopes to omit.
  """
  names: [String!]!
) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    public function handleBuilder(QueryBuilder|EloquentBuilder|Relation $builder, mixed $value): QueryBuilder|EloquentBuilder|Relation
    {
        if (! $value) {
            return $builder;
        }

        $scopes = $this->directiveArgValue('names', $this->nodeName());

        return $builder->withoutGlobalScopes($scopes);
    }
}
