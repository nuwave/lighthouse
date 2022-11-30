<?php

namespace Nuwave\Lighthouse\CacheControl;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class CacheControlDirective extends BaseDirective
{
    public const NAME = 'cacheControl';

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
  Mutually exclusive with `inheritMaxAge = true`.
  """
  maxAge: Int! = 0

  """
  Is the value specific to a single user?
  """
  scope: CacheControlScope! = PUBLIC

  """
  Should the field inherit the `maxAge` of its parent field instead of using the default `maxAge`?
  Mutually exclusive with `maxAge`.
  """
  inheritMaxAge: Boolean! = false
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
