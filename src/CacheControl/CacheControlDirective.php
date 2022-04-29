<?php

namespace Nuwave\Lighthouse\CacheControl;

use Closure;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class CacheControlDirective extends BaseDirective implements FieldMiddleware
{
    /**
     * @var \Nuwave\Lighthouse\CacheControl\CacheControl
     */
    protected $cacheControl;

    public function __construct(CacheControl $cacheControl)
    {
        $this->cacheControl = $cacheControl;
    }

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

    public function handleField(FieldValue $fieldValue, Closure $next)
    {
        $this->cacheControl->addToMaxAgeList(
            $this->directiveArgValue('maxAge') ?? 0
        );
        $this->cacheControl->addToScopeList(
            $this->directiveArgValue('scope') ?? 'PUBLIC'
        );

        return $next($fieldValue);
    }
}
