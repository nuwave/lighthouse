<?php

namespace Nuwave\Lighthouse\Testing;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;

class MockDirective extends BaseDirective implements FieldResolver, DefinedDirective
{
    /**
     * @var array<string, callable>
     */
    protected $mocks;

    /**
     * Register a mock resolver that will be called through this resolver.
     *
     * @param  callable  $mock
     * @param  string  $key
     * @return void
     */
    public function register(callable $mock, string $key): void
    {
        $this->mocks[$key] = $mock;
    }

    /**
     * SDL definition of the directive.
     *
     * @return string
     */
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Allows you to easily hook up a resolver for an endpoint.
"""
directive @mock(
    """
    Specify a unique key for the mock resolver.
    """
    key: String = "default"
) on FIELD_DEFINITION
SDL;
    }

    /**
     * Set a field resolver on the FieldValue.
     *
     * This must call $fieldValue->setResolver() before returning
     * the FieldValue.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $fieldValue
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        return $fieldValue->setResolver(
            function () {
                $key = $this->directiveArgValue('key', 'default');
                $resolver = $this->mocks[$key];

                return $resolver(...func_get_args());
            }
        );
    }
}
