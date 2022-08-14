<?php

namespace Nuwave\Lighthouse\Testing;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;

class MockDirective extends BaseDirective implements FieldResolver
{
    /**
     * @var MockResolverService
     */
    protected $mockResolverService;

    public function __construct(MockResolverService $mockResolverService)
    {
        $this->mockResolverService = $mockResolverService;
    }

    /**
     * SDL definition of the directive.
     */
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Allows you to easily hook up a resolver for an endpoint.
"""
directive @mock(
    """
    Specify a unique key for the mock resolver.
    """
    key: String = "default"
) on FIELD_DEFINITION
GRAPHQL;
    }

    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        $fieldValue->setResolver(function () {
            $key = $this->directiveArgValue('key', 'default');
            $resolver = $this->mockResolverService->get($key);

            return $resolver(...func_get_args());
        });

        return $fieldValue;
    }
}
