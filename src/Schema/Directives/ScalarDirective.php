<?php

namespace Nuwave\Lighthouse\Schema\Directives;

class ScalarDirective extends BaseDirective
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'scalar';
    }

    public static function definition(): string
    {
        return '
"""
Reference a class implementing a scalar definition.
"""
directive @scalar(
  """
  Reference to a class that extends `\GraphQL\Type\Definition\ScalarType`.
  """
  class: String!
) on SCALAR';
    }
}
