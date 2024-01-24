<?php declare(strict_types=1);

namespace Tests\Unit\Execution\Arguments;

use Nuwave\Lighthouse\Execution\Arguments\Argument;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Tests\TestCase;

final class ArgumentSetTest extends TestCase
{
    public function testHasArgument(): void
    {
        $set = new ArgumentSet();

        $this->assertFalse($set->has('foo'));

        $set->arguments['foo'] = new Argument();
        $this->assertFalse($set->has('foo'));

        $arg = new Argument();
        $arg->value = null;
        $set->arguments['foo'] = $arg;
        $this->assertFalse($set->has('foo'));

        $arg->value = false;
        $this->assertTrue($set->has('foo'));

        $arg->value = 'foobar';
        $this->assertTrue($set->has('foo'));
    }

    public function testSingleFieldToArray(): void
    {
        $foo = new Argument();
        $fooValue = 1;
        $foo->value = $fooValue;

        $argumentSet = new ArgumentSet();
        $argumentSet->arguments['foo'] = $foo;

        $this->assertSame(
            [
                'foo' => $fooValue,
            ],
            $argumentSet->toArray(),
        );
    }

    public function testInputObjectToArray(): void
    {
        $foo = new Argument();
        $fooValue = 1;
        $foo->value = $fooValue;

        $fooInput = new ArgumentSet();
        $fooInput->arguments['foo'] = $foo;

        $inputArgument = new Argument();
        $inputArgument->value = $fooInput;

        $argumentSet = new ArgumentSet();
        $argumentSet->arguments['input'] = $inputArgument;

        $this->assertSame(
            [
                'input' => [
                    'foo' => $fooValue,
                ],
            ],
            $argumentSet->toArray(),
        );
    }

    public function testListOfInputObjectsToArray(): void
    {
        $foo = new Argument();
        $fooValue = 1;
        $foo->value = $fooValue;

        $fooInput = new ArgumentSet();
        $fooInput->arguments['foo'] = $foo;

        $inputArgument = new Argument();
        $inputArgument->value = [$fooInput, $fooInput];

        $argumentSet = new ArgumentSet();
        $argumentSet->arguments['input'] = $inputArgument;

        $this->assertSame(
            [
                'input' => [
                    [
                        'foo' => $fooValue,
                    ],
                    [
                        'foo' => $fooValue,
                    ],
                ],
            ],
            $argumentSet->toArray(),
        );
    }

    public function testAddValueAtRootLevel(): void
    {
        $set = new ArgumentSet();
        $set->addValue('foo', 42);

        $this->assertSame(42, $set->arguments['foo']->value);
    }

    public function testAddValueDeep(): void
    {
        $set = new ArgumentSet();
        $set->addValue('foo.bar', 42);

        $foo = $set->arguments['foo']->value;

        $this->assertSame(42, $foo->arguments['bar']->value);
    }
}
