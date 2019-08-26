<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;

class HasManyDirective extends RelationDirective implements FieldResolver, FieldManipulator, DefinedDirective
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'hasMany';
    }

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'SDL'
"""
Corresponds to [the Eloquent relationship HasMany](https://laravel.com/docs/eloquent-relationships#one-to-many).
"""
directive @hasMany(
  """
  Specify the default quantity of elements to be returned.
  """
  defaultCount: Int
  
  """
  Specify the maximum quantity of elements to be returned.
  """
  maxCount: Int
      
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
