<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;

class AuthDirective extends BaseDirective implements FieldResolver
{
    public function __construct(
        protected AuthFactory $authFactory
    ) {}

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Return the currently authenticated user as the result of a query.
"""
directive @auth(
  """
  Specify which guard to use, e.g. "api".
  When not defined, the default from `lighthouse.php` is used.
  """
  guard: String
) on FIELD_DEFINITION
GRAPHQL;
    }

    public function resolveField(FieldValue $fieldValue): callable
    {
        return function (): ?Authenticatable {
            $guard = $this->directiveArgValue('guard', AuthServiceProvider::guard());
            assert(is_string($guard) || is_null($guard));

            return $this
                ->authFactory
                ->guard($guard)
                ->user();
        };
    }
}
