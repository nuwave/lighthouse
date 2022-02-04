<?php

namespace Tests\Unit\Schema\Directives;

use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;
use Tests\Unit\Schema\Directives\Fixtures\Foo;

class MethodDirectiveTest extends TestCase
{
    protected $schema = /** @lang GraphQL */ '
    type Query {
        foo: Foo @mock
    }
    ';

    public function testDefaultToFieldNameAsMethodName(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Foo {
            bar: ID @method
        }
        ';

        $foo = $this->mockFoo();
        $foo->expects($this->once())
            ->method('bar')
            ->with();

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo {
                bar
            }
        }
        ');
    }

    public function testWillPreferExplicitName(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Foo {
            asdf: ID @method(name: "bar")
        }
        ';

        $foo = $this->mockFoo();
        $foo->expects($this->once())
            ->method('bar');

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo {
                asdf
            }
        }
        ');
    }

    public function testPassArgsInLexicalOrderOfDefinition(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Foo {
            bar(
                first: ID
                second: ID
            ): ID @method
        }
        ';

        $foo = $this->mockFoo();
        $foo->expects($this->once())
            ->method('bar')
            ->with(1, 2);

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo {
                bar(
                    second: 2
                    first: 1
                )
            }
        }
        ');
    }

    public function testPassOrderedDefaultsToNull(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Foo {
            bar(
                baz: ID
            ): ID @method
        }
        ';

        $foo = $this->mockFoo();
        $foo->expects($this->once())
            ->method('bar')
            ->with(null);

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo {
                bar
            }
        }
        ');
    }

    protected function mockFoo(): MockObject
    {
        $foo = $this->createMock(Foo::class);

        $this->mockResolver($foo);

        return $foo;
    }
}
