<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Execution\DataLoader\RelationLoader;
use Nuwave\Lighthouse\Execution\DataLoader\SimpleRelationLoader;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class WithDirective extends WithRelationDirective implements FieldMiddleware
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Eager-load an Eloquent relation.
"""
directive @with(
  """
  Specify the relationship method name in the model class,
  if it is named different from the field in the schema.
  """
  relation: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]
) repeatable on FIELD_DEFINITION
GRAPHQL;
    }

    protected function relationName(): string
    {
        return $this->directiveArgValue('relation')
            ?? $this->nodeName();
    }

    protected function relationLoader(ResolveInfo $resolveInfo): RelationLoader
    {
        return new SimpleRelationLoader(
            $this->decorateBuilder($resolveInfo)
        );
    }
}
