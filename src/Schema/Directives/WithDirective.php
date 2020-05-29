<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Execution\DataLoader\RelationBatchLoader;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class WithDirective extends WithRelationDirective implements FieldMiddleware, DefinedDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
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
) on FIELD_DEFINITION
SDL;
    }

    public function batchLoaderClass(): string
    {
        return RelationBatchLoader::class;
    }

    public function relationName(): string
    {
        return $this->directiveArgValue('relation', $this->nodeName());
    }
}
