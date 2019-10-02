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
     * @param  callable|null  $resolver
     * @return \PHPUnit\Framework\MockObject\Builder\InvocationMocker
     */
    protected function mockResolver(callable $resolver = null): InvocationMocker
    {
        $mock = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['__invoke'])
            ->getMock();

        $this->registerMockResolver($mock);

        $method = $mock
            ->expects($this->once())
            ->method('__invoke');

        if ($resolver) {
            $method->willReturnCallback($resolver);
        }

        return $method;
    }

    /**
     * Register a mock resolver that will be called through the @mock directive.
     *
     * @param  callable  $mock
     * @return void
     */
    protected function registerMockResolver(callable $mock): void
    {
        /** @var \Nuwave\Lighthouse\Testing\MockDirective $mockDirective */
        $mockDirective = app(MockDirective::class);
        $mockDirective->register($mock);
    }
}
