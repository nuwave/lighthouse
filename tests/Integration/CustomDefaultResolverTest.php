<?php

namespace Tests\Integration;

use GraphQL\Executor\Executor;
use Tests\TestCase;

class CustomDefaultResolverTest extends TestCase
{
    const CUSTOM_RESOLVER_RESULT = 123;

    protected $schema = /** @lang GraphQL */'
    type Query {
        foo: Foo @field(resolver: "Tests\\\\Integration\\\\CustomDefaultResolverTest@resolve")
    }

    type Foo {
        bar: Int
    }
    ';

    public function resolve(): array
    {
        return [
            'bar' => 'This should not be returned.',
        ];
    }

    public function testCanSpecifyACustomDefaultResolver(): void
    {
        $previous = Executor::getDefaultFieldResolver();

        Executor::setDefaultFieldResolver(function (): int {
            return self::CUSTOM_RESOLVER_RESULT;
        });

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo {
                bar
            }
        }
        ')->assertJson([
            'data' => [
                'foo' => [
                    'bar' => self::CUSTOM_RESOLVER_RESULT,
                ],
            ],
        ]);

        Executor::setDefaultFieldResolver($previous);
    }
}
