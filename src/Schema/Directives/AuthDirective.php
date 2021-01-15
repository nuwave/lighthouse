<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;

class AuthDirective extends BaseDirective implements FieldResolver
{
    /**
     * @var \Illuminate\Contracts\Auth\Factory
     */
    protected $authFactory;

    public function __construct(AuthFactory $authFactory)
    {
        $this->authFactory = $authFactory;
    }

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

    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        return $fieldValue->setResolver(
            function (): ?Authenticatable {
                /** @var string|null $guard */
                $guard = $this->directiveArgValue('guard', config('lighthouse.guard'));

                // @phpstan-ignore-next-line phpstan does not know about App\User, which implements Authenticatable
                return $this
                    ->authFactory
                    ->guard($guard)
                    ->user();
            }
        );
    }
}
