<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Execution\DataLoader\RelationBatchLoader;
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

    public function relationName(): string
    {
        return $this->directiveArgValue('relation')
            ?? $this->nodeName();
    }

    protected function loadRelation(RelationBatchLoader $loader, string $relationName, ResolveInfo $resolveInfo, Model $parent): Deferred
    {
        if (!$loader->hasRelationMeta($relationName)) {
            $loader->registerRelationMeta($relationName, $this->relationMeta($resolveInfo));
        }

        return $loader->relation($relationName, $parent);
    }
}
