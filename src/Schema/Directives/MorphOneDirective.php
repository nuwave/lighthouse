<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;

class MorphOneDirective extends RelationDirective implements FieldResolver, DefinedDirective
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'morphOne';
    }

    /**
     * SDL definition of the directive.
     *
     * @return string
     */
    public static function definition()
    {
        return /* @lang GraphQL */ <<<'SDL'
"""
Corresponds to [Eloquent's MorphOne-Relationship](https://laravel.com/docs/5.8/eloquent-relationships#one-to-one-polymorphic-relations).
"""
directive @morphOne(      
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
