<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\DataLoader\RelationCountLoader;
use Nuwave\Lighthouse\Execution\DataLoader\RelationLoader;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class WithCountDirective extends WithRelationDirective implements FieldMiddleware
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Eager-load the count of an Eloquent relation if the field is queried.

Note that this does not return a value for the field, the count is simply
prefetched, assuming it is used to compute the field value. Use `@count`
if the field should simply return the relation count.
"""
directive @withCount(
  """
  Specify the relationship method name in the model class.
  """
  relation: String!

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]
) repeatable on FIELD_DEFINITION
GRAPHQL;
    }

    protected function relationName(): string
    {
        $relation = $this->directiveArgValue('relation');
        if (! $relation) {
            throw new DefinitionException("You must specify the argument relation in the {$this->name()} directive on {$this->definitionNode->name->value}.");
        }

        return $relation;
    }

    protected function relationLoader(ResolveInfo $resolveInfo): RelationLoader
    {
        return new RelationCountLoader(
            $this->decorateBuilder($resolveInfo)
        );
    }
}
