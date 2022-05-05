<?php

namespace Nuwave\Lighthouse\CacheControl;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class CacheControlDirective extends BaseDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Influences the HTTP `Cache-Control` headers of the response.
"""
directive @cacheControl(
  """
  The maximum amount of time the field's cached value is valid, in seconds.
  0 means the field is not cacheable.
  """
  maxAge: Int! = 0

  """
  Is the value specific to a single user?
  """
  scope: CacheControlScope! = PUBLIC
) on FIELD_DEFINITION | OBJECT | INTERFACE | UNION

"""
Options for the `scope` argument of `@cacheControl`.
"""
enum CacheControlScope {
    """
    The value is the same for each user.
    """
    PUBLIC

    """
    The value is specific to a single user.
    """
    PRIVATE
}
GRAPHQL;
    }
}
