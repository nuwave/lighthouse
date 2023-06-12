<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Testing;

use Illuminate\Container\Container;
use PHPUnit\Framework\MockObject\Builder\InvocationMocker;
use PHPUnit\Framework\MockObject\MockObject;

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
     * @return \PHPUnit\Framework\MockObject\Builder\InvocationMocker<\stdClass>
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
     * Register a resolver for `@mock`.
     *
     * @param  \PHPUnit\Framework\MockObject\Rule\InvocationOrder  $invocationOrder
     *
     * @return \PHPUnit\Framework\MockObject\Builder\InvocationMocker<\stdClass>
     */
    protected function mockResolverExpects(object $invocationOrder, string $key = 'default'): InvocationMocker
    {
        /** @var MockObject|callable $mock */
        $mock = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['__invoke'])
            ->getMock();

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
