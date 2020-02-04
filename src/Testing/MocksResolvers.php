<?php

namespace Nuwave\Lighthouse\Testing;

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
     * @return \Nuwave\Lighthouse\Testing\InvocationMocker
     */
    protected function mockResolver($resolverOrValue = null, string $key = 'default'): InvocationMocker
    {
        $mock = $this
            ->getMockBuilder(MockResolver::class)
            ->getMock();

        $this->registerMockResolver($mock, $key);

        return new InvocationMocker($mock, $resolverOrValue);
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
