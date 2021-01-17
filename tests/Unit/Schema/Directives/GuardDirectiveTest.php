<?php

namespace Tests\Unit\Schema\Directives;

use Nuwave\Lighthouse\Exceptions\AuthenticationException;
use Tests\TestCase;
use Tests\Utils\Models\User;

class GuardDirectiveTest extends TestCase
{
    public function testGuardsWithDefault(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Int @guard
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertJson([
            'errors' => [
                [
                    'message' => AuthenticationException::MESSAGE,
                ],
            ],
        ]);
    }

    public function testGuardsWithApi(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Int @guard(with: "api")
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertJson([
            'errors' => [
                [
                    'message' => AuthenticationException::MESSAGE,
                    'extensions' => [
                        'guards' => [
                            'api',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testPassesOneFieldButThrowsInAnother(): void
    {
        $this->be(new User());

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Int @guard
            bar: String @guard(with: "api")
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
            bar
        }
        ')->assertJson([
            'data' => [
                'foo' => \Tests\Utils\Queries\Foo::THE_ANSWER,
                'bar' => null,
            ],
            'errors' => [
                [
                    'path' => [
                        'bar',
                    ],
                    'message' => AuthenticationException::MESSAGE,
                ],
            ],
        ]);
    }
}
