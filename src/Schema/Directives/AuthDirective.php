<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;

class AuthDirective extends BaseDirective implements DefinedDirective, FieldResolver
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
        return /** @lang GraphQL */ <<<'SDL'
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
SDL;
    }

    /**
     * Resolve the field directive.
     */
    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        /** @var string|null $guard */
        $guard = $this->directiveArgValue('guard', config('lighthouse.guard'));

        return $fieldValue->setResolver(
            function () use ($guard): ?Authenticatable {
                // @phpstan-ignore-next-line phpstan does not know about App\User, which implements Authenticatable
                return $this
                    ->authFactory
                    ->guard($guard)
                    ->user();
            }
        );
    }
}
