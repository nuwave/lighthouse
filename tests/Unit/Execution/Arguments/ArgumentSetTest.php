<?php declare(strict_types=1);

namespace Tests\Unit\Execution\Arguments;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\Arguments\Argument;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Tests\TestCase;

final class ArgumentSetTest extends TestCase
{
    public function testHas(): void
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

    public function testExists(): void
    {
        $set = new ArgumentSet();

        $this->assertFalse($set->exists('foo'));

        $set->arguments['foo'] = new Argument();
        $this->assertTrue($set->exists('foo'));

        $arg = new Argument();
        $arg->value = null;
        $set->arguments['foo'] = $arg;
        $this->assertTrue($set->exists('foo'));

        $arg->value = false;
        $this->assertTrue($set->exists('foo'));

        $arg->value = 'foobar';
        $this->assertTrue($set->exists('foo'));
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

        $argument = $set->arguments['foo'];
        $this->assertSame(42, $argument->value);
        $this->assertNull($argument->type);
        $this->assertEmpty($argument->directives);
        $this->assertNull($argument->resolver);
    }

    public function testAddValueDeep(): void
    {
        $set = new ArgumentSet();
        $set->addValue('foo.bar', 42);

        $foo = $set->arguments['foo'];
        $this->assertNull($foo->type);
        $this->assertEmpty($foo->directives);
        $this->assertNull($foo->resolver);

        $fooValue = $foo->value;
        $this->assertInstanceOf(ArgumentSet::class, $fooValue);

        $bar = $fooValue->arguments['bar'];
        $this->assertSame(42, $bar->value);
        $this->assertNull($bar->type);
        $this->assertEmpty($bar->directives);
        $this->assertNull($bar->resolver);
    }

    public function testAddValueWithWildcardInjectsIntoEachListElement(): void
    {
        $set = new ArgumentSet();
        $set->arguments['create'] = $this->listOfArgumentSets(
            $this->argumentSetWithSibling('a'),
            $this->argumentSetWithSibling('b'),
        );

        $set->addValue('create.*.user_id', 123);

        $create = $set->arguments['create']->value;
        $this->assertSame(123, $create[0]->arguments['user_id']->value);
        $this->assertSame('a', $create[0]->arguments['sibling']->value);
        $this->assertSame(123, $create[1]->arguments['user_id']->value);
        $this->assertSame('b', $create[1]->arguments['sibling']->value);
    }

    public function testAddValueWithWildcardSkipsWhenListArgumentIsMissing(): void
    {
        $set = new ArgumentSet();
        $set->addValue('create.*.user_id', 123);

        $this->assertFalse($set->exists('create'));
    }

    public function testAddValueWithWildcardSkipsWhenNestedContainerIsMissing(): void
    {
        $set = new ArgumentSet();
        $set->addValue('tasks.create.*.user_id', 123);

        // The intermediary `tasks` input is created just like plain dot notation would,
        // but nothing is injected because `create` was never provided by the client.
        $this->assertTrue($set->exists('tasks'));

        $tasks = $set->arguments['tasks']->value;
        $this->assertInstanceOf(ArgumentSet::class, $tasks);
        $this->assertFalse($tasks->exists('create'));
    }

    public function testAddValueWithWildcardSkipsWhenListIsEmpty(): void
    {
        $set = new ArgumentSet();
        $set->arguments['create'] = $this->listOfArgumentSets();

        $set->addValue('create.*.user_id', 123);

        $this->assertSame([], $set->arguments['create']->value);
    }

    public function testAddValueWithWildcardDoesNotOverwriteUnrelatedFalsyValues(): void
    {
        $element = $this->argumentSetWithSibling(false);

        $set = new ArgumentSet();
        $set->arguments['create'] = $this->listOfArgumentSets($element);

        $set->addValue('create.*.user_id', 123);

        $create = $set->arguments['create']->value;
        $this->assertSame(123, $create[0]->arguments['user_id']->value);
        $this->assertFalse($create[0]->arguments['sibling']->value);
    }

    public function testAddValueWithWildcardThrowsWhenValueIsNotAList(): void
    {
        $set = new ArgumentSet();
        $create = new Argument();
        $create->value = 'not-a-list';
        $set->arguments['create'] = $create;

        $this->expectException(DefinitionException::class);
        $set->addValue('create.*.user_id', 123);
    }

    public function testAddValueWithWildcardThrowsWhenListElementIsNotAnArgumentSet(): void
    {
        $set = new ArgumentSet();
        $create = new Argument();
        $create->value = ['not-an-argument-set'];
        $set->arguments['create'] = $create;

        $this->expectException(DefinitionException::class);
        $set->addValue('create.*.user_id', 123);
    }

    public function testAddValueWithWildcardThrowsWhenUsedAsFinalPathSegment(): void
    {
        $set = new ArgumentSet();
        $set->arguments['create'] = $this->listOfArgumentSets($this->argumentSetWithSibling('a'));

        $this->expectException(DefinitionException::class);
        $set->addValue('create.*', 123);
    }

    /** Build a list element that already carries an unrelated argument, to prove injection leaves it untouched. */
    private function argumentSetWithSibling(mixed $value): ArgumentSet
    {
        $sibling = new Argument();
        $sibling->value = $value;

        $argumentSet = new ArgumentSet();
        $argumentSet->arguments['sibling'] = $sibling;

        return $argumentSet;
    }

    private function listOfArgumentSets(ArgumentSet ...$argumentSets): Argument
    {
        $argument = new Argument();
        $argument->value = $argumentSets;

        return $argument;
    }
}
