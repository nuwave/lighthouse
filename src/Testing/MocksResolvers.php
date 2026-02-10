<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Testing;

use Illuminate\Container\Container;
use PHPUnit\Framework\MockObject\Builder\InvocationMocker;
use PHPUnit\Framework\MockObject\InvocationStubber;
use PHPUnit\Framework\MockObject\Rule\InvocationOrder;

// PHPUnit 9, 10, 11
if (class_exists(InvocationMocker::class)) {
    /**
     * @mixin \PHPUnit\Framework\TestCase
     */
    trait MocksResolvers
    {
        /**
         * Create and register a PHPUnit mock to be called through the `@mock` directive.
         *
         * @param  callable|mixed|null  $resolverOrValue
         *
         * @return \PHPUnit\Framework\MockObject\Builder\InvocationMocker<\Nuwave\Lighthouse\Testing\MockableResolver>
         */
        protected function mockResolver(mixed $resolverOrValue = null, string $key = 'default'): InvocationMocker
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
         * Register a resolver for `@mock`.
         *
         * @param  \PHPUnit\Framework\MockObject\Rule\InvocationOrder  $invocationOrder
         *
         * @return \PHPUnit\Framework\MockObject\Builder\InvocationMocker<\Nuwave\Lighthouse\Testing\MockableResolver>
         */
        protected function mockResolverExpects(object $invocationOrder, string $key = 'default'): InvocationMocker
        {
            $mock = $this->createMock(MockableResolver::class);

            $this->registerMockResolver($mock, $key);

            // @phpstan-ignore-next-line generic type mismatch
            return $mock
                ->expects($invocationOrder)
                ->method('__invoke');
        }

        /** Register a mock resolver that will be called through the `@mock` directive. */
        protected function registerMockResolver(callable $mock, string $key): void
        {
            $mockResolverService = Container::getInstance()->make(MockResolverService::class);
            $mockResolverService->register($mock, $key);
        }
    }
} else {
    // PHPUnit 12+
    /**
     * @mixin \PHPUnit\Framework\TestCase
     */
    trait MocksResolvers
    {
        /**
         * Create and register a PHPUnit mock to be called through the `@mock` directive.
         *
         * @param  callable|mixed|null  $resolverOrValue
         */
        protected function mockResolver(mixed $resolverOrValue = null, string $key = 'default'): InvocationStubber
        {
            $method = $this->mockResolverExpects($this->atLeastOnce(), $key);

            if (is_callable($resolverOrValue)) {
                $method->willReturnCallback($resolverOrValue);
            } else {
                $method->willReturn($resolverOrValue);
            }

            return $method;
        }

        /** Register a resolver for `@mock`. */
        protected function mockResolverExpects(InvocationOrder $invocationOrder, string $key = 'default'): InvocationStubber
        {
            $mock = $this->createMock(MockableResolver::class);

            $this->registerMockResolver($mock, $key);

            return $mock
                ->expects($invocationOrder)
                ->method('__invoke');
        }

        /** Register a mock resolver that will be called through the `@mock` directive. */
        protected function registerMockResolver(callable $mock, string $key): void
        {
            $mockResolverService = Container::getInstance()->make(MockResolverService::class);
            $mockResolverService->register($mock, $key);
        }
    }
}
