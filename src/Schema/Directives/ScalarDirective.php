<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;

class ScalarDirective extends BaseDirective implements DefinedDirective
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
        return /* @lang GraphQL */ <<<'SDL'
"""
Reference a class implementing a scalar definition.
"""
directive @scalar(
  """
  Reference to a class that extends `\GraphQL\Type\Definition\ScalarType`.
  """
  class: String!
) on SCALAR
SDL;
    }
}
