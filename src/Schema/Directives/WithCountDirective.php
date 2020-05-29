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

The name of the field is assumed to be `${RELATION}_count`,
for example if the relation is called `foo`, the name of the
field should be `foo_count`.
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
        $relation = $this->directiveArgValue('relation');

        // Without a provided relation, the node name is assummed to be an actual
        // relation on the model.
        if (! $relation) {
            return Str::before($this->nodeName(), '_count');
        }

        // With a provided relation and differing node name, Laravel should alias
        // the relation as the node name when querying th database.
        if ($relation && $relation !== $this->nodeName()) {
            return "{$relation} as {$this->nodeName()}";
        }

        return Str::before($this->nodeName(), '_count');
    }
}
