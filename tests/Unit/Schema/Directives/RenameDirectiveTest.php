<?php

namespace Tests\Unit\Schema\Directives;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Tests\TestCase;

class RenameDirectiveTest extends TestCase
{
    public function testRenameField(): void
    {
        $this->mockResolver(function () {
            return [
                'baz' => 'asdf'
            ];
        });

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Foo @mock
        }

        type Foo {
            bar: String! @rename(attribute: "baz")
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo {
                bar
            }
        }
        ')->assertJson([
            'data' => [
                'foo' => [
                    'bar' => 'asdf',
                ],
            ],
        ]);
    }

    public function testThrowsAnExceptionIfNoAttributeDefined(): void
    {
        $this->expectException(DefinitionException::class);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: String! @rename
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            fooBar
        }
        ');
    }

    public function testRenameArgument(): void
    {
        $this->mockResolver(function ($root, array $args) {
            return $args === ['bar' => 'something'];
        });

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(
                baz: String @rename(attribute: "bar")
            ): Boolean @mock
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo(baz: "something")
        }
        ')->assertJson([
            'data' => [
                'foo' => true,
            ],
        ]);
    }
}
