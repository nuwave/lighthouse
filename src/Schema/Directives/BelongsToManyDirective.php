<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;

class BelongsToManyDirective extends RelationDirective implements FieldResolver, FieldManipulator
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'belongsToMany';
    }

    public static function definition(): string
    {
        return '
directive @belongsToMany(
  """
  Which pagination style to use.
  Allowed values: paginator, connection.
  """
  type: String = "paginator"
  
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
  
  """
  Specify a custom type that implements the Edge interface
  to extend edge object.
  """
  edgeType: String
) on FIELD_DEFINITION';
    }
}
