<?php

namespace Tests\Integration;

use Tests\TestCase;
use GraphQL\Executor\Executor;

class CustomDefaultResolverTest extends TestCase
{
    const CUSTOM_RESOLVER_RESULT = 123;

    protected $schema = '
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

    /**
     * @test
     */
    public function itCanSpecifyACustomDefaultResolver(): void
    {
        $previous = Executor::getDefaultFieldResolver();

        Executor::setDefaultFieldResolver(function (): int {
            return self::CUSTOM_RESOLVER_RESULT;
        });

        $this->graphQL('
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
