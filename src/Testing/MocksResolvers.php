<?php

namespace Nuwave\Lighthouse\Testing;

use PHPUnit\Framework\MockObject\MockBuilder;
use PHPUnit\Framework\MockObject\Builder\InvocationMocker;

trait MocksResolvers
{
    abstract protected function getMockBuilder($originalClassName): MockBuilder;

    /**
     * Create and register a PHPUnit mock to be called through the @mock directive.
     *
     * @return \PHPUnit\Framework\MockObject\Builder\InvocationMocker
     */
    protected function mockResolver(): InvocationMocker
    {
        $mock = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['__invoke'])
            ->getMock();

        $this->registerMockResolver($mock);

        return $mock
            ->expects($this->once())
            ->method('__invoke');
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
