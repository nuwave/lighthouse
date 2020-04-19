<?php

namespace Nuwave\Lighthouse\Testing;

use PHPUnit\Framework\MockObject\Builder\InvocationMocker;

/**
 * @mixin \PHPUnit\Framework\TestCase
 */
trait MocksResolvers
{
    /**
     * Create and register a PHPUnit mock to be called through the @mock directive.
     *
     * @param  callable|mixed|null  $resolverOrValue
     */
    protected function mockResolver($resolverOrValue = null, string $key = 'default'): InvocationMocker
    {
        $method = $this->mockResolverExpects($this->atLeastOnce(), $key);

        if (is_callable($resolverOrValue)) {
            $method->willReturnCallback($resolverOrValue);
        } else {
            $method->willReturn($resolverOrValue);
        }

        return $method;
    }

    /**
     * Register a resolver for @mock.
     *
     * @param  \PHPUnit\Framework\MockObject\Rule\InvocationOrder  $invocationOrder
     */
    protected function mockResolverExpects(/* TODO add strong type hint when bumping PHPUnit */ $invocationOrder, string $key = 'default'): InvocationMocker
    {
        $mock = $this->createMock(MockResolver::class);

        $this->registerMockResolver($mock, $key);

        return $mock
            ->expects($invocationOrder)
            ->method('__invoke');
    }

    /**
     * Register a mock resolver that will be called through the @mock directive.
     */
    protected function registerMockResolver(callable $mock, string $key): void
    {
        /** @var \Nuwave\Lighthouse\Testing\MockDirective $mockDirective */
        $mockDirective = app(MockDirective::class);
        $mockDirective->register($mock, $key);
    }
}
