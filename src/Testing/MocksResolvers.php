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
     * @param  string  $key
     * @return \PHPUnit\Framework\MockObject\Builder\InvocationMocker
     */
    protected function mockResolver($resolverOrValue = null, string $key = 'default'): InvocationMocker
    {
        $mock = $this->getMockBuilder(MockResolver::class)
            ->getMock();

        $this->registerMockResolver($mock, $key);

        $method = $mock
            ->expects($this->any())
            ->method('__invoke');

        if (is_callable($resolverOrValue)) {
            $method->willReturnCallback($resolverOrValue);
        } else {
            $method->willReturn($resolverOrValue);
        }

        return $method;
    }

    /**
     * Register a mock resolver that will be called through the @mock directive.
     *
     * @param  callable  $mock
     * @param  string  $key
     * @return void
     */
    protected function registerMockResolver(callable $mock, string $key): void
    {
        /** @var \Nuwave\Lighthouse\Testing\MockDirective $mockDirective */
        $mockDirective = app(MockDirective::class);
        $mockDirective->register($mock, $key);
    }
}
