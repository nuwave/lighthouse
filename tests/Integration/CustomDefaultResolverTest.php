<?php

namespace Tests\Integration;

use GraphQL\Executor\Executor;
use Tests\TestCase;

class CustomDefaultResolverTest extends TestCase
{
    const CUSTOM_RESOLVER_RESULT = 123;

    public function testCanSpecifyACustomDefaultResolver(): void
    {
        $this->mockResolver([
            'bar' => 'should not be returned',
        ]);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Foo @mock
        }

        type Foo {
            bar: Int
        }
        ';

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
