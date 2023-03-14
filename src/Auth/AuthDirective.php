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
        protected AuthFactory $authFactory,
    ) {}

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Return the currently authenticated user as the result of a query.
"""
directive @auth(
  """
  Specify which guards to use, e.g. ["api"].
  When not defined, the default from `lighthouse.php` is used.
  """
  guards: [String!]
) on FIELD_DEFINITION
GRAPHQL;
    }

    public function resolveField(FieldValue $fieldValue): callable
    {
        return function (): ?Authenticatable {
            $guards = $this->directiveArgValue('guards', AuthServiceProvider::guards());
            assert(is_array($guards));

            return $this->authenticatedUser($guards);
        };
    }

    /**
     * Return the first logged-in user to any of the given guards.
     *
     * @param  array<string>  $guards
     */
    protected function authenticatedUser(array $guards): ?Authenticatable
    {
        foreach ($guards as $guard) {
            $user = $this->authFactory->guard($guard)
                ->user();
            if ($user !== null) {
                return $user;
            }
        }

        return null;
    }
}
