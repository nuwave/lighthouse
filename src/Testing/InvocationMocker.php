<?php

namespace Nuwave\Lighthouse\Testing;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Rule\InvocationOrder;
use PHPUnit\Framework\MockObject\Rule\InvokedAtLeastOnce;

/**
 * @method \Nuwave\Lighthouse\Testing\InvocationMocker expects(InvocationOrder $invocationRule)
 * @mixin \PHPUnit\Framework\MockObject\Builder\InvocationMocker
 */
class InvocationMocker
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $mockObject;

    /**
     * @var callable|mixed|null
     */
    protected $resolverOrValue;

    /**
     * InvocationMocker constructor.
     *
     * @param  \PHPUnit\Framework\MockObject\MockObject  $mockObject
     * @param  callable|mixed|null  $resolverOrValue  $resolverOrValue
     *
     * @return void
     */
    public function __construct(MockObject $mockObject, $resolverOrValue = null)
    {
        $this->mockObject = $mockObject;
        $this->resolverOrValue = $resolverOrValue;
    }

    public function __call($name, $arguments)
    {
        // We do this little dance in order to allow setting a custom
        // invocation rule without having to define the method __invoke.
        if ($name === 'expects') {
            $invocationMocker = $this->mockObject->expects($arguments[0]);
        } else {
            $invocationMocker = $this->mockObject->expects(new InvokedAtLeastOnce());
        }

        $method = $invocationMocker->method('__invoke');

        if (is_callable($this->resolverOrValue)) {
            $method->willReturnCallback($this->resolverOrValue);
        } else {
            $method->willReturn($this->resolverOrValue);
        }

        return $method;
    }
}
