<?php

namespace Nuwave\Lighthouse\Schema\Directives;

class EnumDirective extends BaseDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Assign an internal value to an enum key.
When dealing with the Enum type in your code,
you will receive the defined value instead of the string key.
"""
directive @enum(
  """
  The internal value of the enum key.
  """
  value: EnumValue
) on ENUM_VALUE

"""
Any constant literal value: https://graphql.github.io/graphql-spec/draft/#sec-Input-Values
"""
scalar EnumValue
GRAPHQL;
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
