<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Support\Str;
use Nuwave\Lighthouse\Execution\DataLoader\RelationCountBatchLoader;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class WithCountDirective extends WithRelationDirective implements FieldMiddleware, DefinedDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Eager-load the count of an Eloquent relation.
"""
directive @withCount(
  """
  Specify the relationship method name in the model class,
  if the field name does not match the convention `${RELATION}_count`.
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
        return RelationCountBatchLoader::class;
    }

    public function relationName(): string
    {
        if ($relation = $this->directiveArgValue('relation')) {
            return $relation;
        }

        return Str::before($this->nodeName(), '_count');
    }
}
