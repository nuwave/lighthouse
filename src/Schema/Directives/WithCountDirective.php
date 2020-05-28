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
Eager-load a count of an Eloquent relation.
"""
directive @withCount(
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

    /**
     * The name of the batch loader to use.
     */
    public function batchLoaderName(): string
    {
        return RelationCountBatchLoader::class;
    }

    /**
     * The the name of the relation to be loaded.
     */
    protected function relationName(): string
    {
        $relation = $this->directiveArgValue('relation');

        if (! $relation && Str::endsWith($this->nodeName(), '_count')) {
            return str_replace('_count', '', $this->nodeName());
        }

        return "{$relation} as {$this->nodeName()}";
    }
}
