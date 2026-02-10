<?php declare(strict_types=1);

namespace Tests\Unit\Schema\Directives;

use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;
use Tests\Utils\MockableFoo;

final class MethodDirectiveTest extends TestCase
{
    protected string $schema = /** @lang GraphQL */ <<<'GRAPHQL'
    type Query {
        foo: Foo @mock
    }
    GRAPHQL;

    public function testDefaultToFieldNameAsMethodName(): void
    {
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Foo {
            bar: ID @method
        }
        GRAPHQL;

        $foo = $this->mockFoo();
        $foo->expects($this->once())
            ->method('bar')
            ->with();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo {
                bar
            }
        }
        GRAPHQL);
    }

    public function testWillPreferExplicitName(): void
    {
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Foo {
            asdf: ID @method(name: "bar")
        }
        GRAPHQL;

        $foo = $this->mockFoo();
        $foo->expects($this->once())
            ->method('bar');

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo {
                asdf
            }
        }
        GRAPHQL);
    }

    public function testPassArgsInLexicalOrderOfDefinition(): void
    {
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Foo {
            bar(
                first: ID
                second: ID
            ): ID @method
        }
        GRAPHQL;

        $foo = $this->mockFoo();
        $foo->expects($this->once())
            ->method('bar')
            ->with(1, 2);

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo {
                bar(
                    second: 2
                    first: 1
                )
            }
        }
        GRAPHQL);
    }

    public function testPassOrderedDefaultsToNull(): void
    {
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Foo {
            bar(
                baz: ID
            ): ID @method
        }
        GRAPHQL;

        $foo = $this->mockFoo();
        $foo->expects($this->once())
            ->method('bar')
            ->with(null);

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo {
                bar
            }
        }
        GRAPHQL);
    }

    private function mockFoo(): MockObject
    {
        $foo = $this->createMock(MockableFoo::class);

        $this->mockResolver($foo);

        return $foo;
    }
}
