<?php

namespace Nuwave\Lighthouse\CacheControl;

use Closure;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class CacheControlDirective extends BaseDirective implements FieldMiddleware
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Set the HTTP Cache-Control headers of the response.
"""
directive @cacheControl(
  """
  The maximum amount of time the field's cached value is valid, in seconds. 
  The default value is 0(no-cache), but you can set a different default from config.
  """
  maxAge: Int = 0

  """
  If PRIVATE, the field's value is specific to a single user. 
  The default value is PUBLIC.
  """
  scope: CacheControlScope = PUBLIC  
) on FIELD_DEFINITION | OBJECT | INTERFACE | UNION

"""
Options for the `scope` argument of `@cacheControl`.
"""
enum CacheControlScope {
    """
    The HTTP Cache-Control header set to public.
    """
    PUBLIC

    """
    The HTTP Cache-Control header set to private.
    """
    PRIVATE
}
GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue, Closure $next)
    {
        $maxAge = $this->directiveArgValue('maxAge') ?? 0;
        $scope = $this->directiveArgValue('scope') ?? 'public';

        app(CacheControl::class)->addToMaxAgeList($maxAge);
        app(CacheControl::class)->addToScopeList($scope);

        return $next($fieldValue);
    }
}
