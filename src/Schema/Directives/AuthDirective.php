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

    /**
     * AuthDirective constructor.
     *
     * @param  \Illuminate\Contracts\Auth\Factory  $authFactory
     * @return void
     */
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
  Use a particular guard to retreive the user.
  """
  guard: String
) on FIELD_DEFINITION
SDL;
    }

    /**
     * Resolve the field directive.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $fieldValue
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        /** @var string|null $guard */
        $guard = $this->directiveArgValue('guard');

        return $fieldValue->setResolver(
            function () use ($guard): ?Authenticatable {
                return $this
                    ->authFactory
                    ->guard($guard)
                    ->user();
            }
        );
    }
}
