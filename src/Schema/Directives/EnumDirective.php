<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;

class EnumDirective extends BaseDirective implements DefinedDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Assign an internal value to an enum key.
When dealing with the Enum type in your code,
you will receive the defined value instead of the string key.
"""
directive @enum(
  """
  The internal value of the enum key.
  You can use any constant literal value: https://graphql.github.io/graphql-spec/draft/#sec-Input-Values
  """
  value: Mixed
) on ENUM_VALUE
SDL;
    }

    /**
     * Get the internal value of the enum key.
     *
     * @return mixed|null
     */
    public function value()
    {
        return $this->directiveArgValue('value');
    }
}
