<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;

class MorphToDirective extends RelationDirective implements FieldResolver, DefinedDirective
{
    /**
     * SDL definition of the directive.
     *
     * @return string
     */
    public static function definition()
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Corresponds to [Eloquent's MorphTo-Relationship](https://laravel.com/docs/5.8/eloquent-relationships#one-to-one-polymorphic-relations).
"""
directive @morphTo(
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
}
