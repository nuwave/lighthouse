<?php

namespace Tests\Unit\Schema\Directives;

use Tests\TestCase;
use Tests\Utils\Queries\Foo;
use Nuwave\Lighthouse\Support\Contracts\GlobalId;

class GlobalIdDirectiveTest extends TestCase
{
    /**
     * @var \Nuwave\Lighthouse\Execution\Utils\GlobalId
     */
    protected $globalId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->globalId = $this->app->make(GlobalId::class);
    }

    /**
     * @test
     */
    public function itDecodesGlobalId(): void
    {
        $this->schema = '
        type Query {
            foo: String! @globalId
        }
        ';

        $this->graphQL('
        {
            foo
        }        
        ')->assertJson([
            'data' => [
                'foo' => $this->globalId->encode('Query', Foo::THE_ANSWER),
            ],
        ]);
    }

    /**
     * @test
     */
    public function itDecodesGlobalIds(): void
    {
        $this->schema = "
        type Query {
            foo(
                type: ID! @globalId(decode: TYPE)
                id: ID! @globalId(decode: ID)
                array: ID! @globalId
            ): Foo @field(resolver: \"{$this->qualifyTestResolver()}\")
        }
        
        type Foo {
            type: String!
            id: ID!
            array: [String!]!
        }
        ";

        $globalId = $this->globalId->encode('Foo', 'bar');

        $this->graphQL("
        {
            foo(
                type: \"{$globalId}\"
                id: \"{$globalId}\"
                array: \"{$globalId}\"
            ) {
                id
                type
                array
            }
        }        
        ")->assertJson([
            'data' => [
                'foo' => [
                    'type' => 'Foo',
                    'id' => 'bar',
                    'array' => [
                        'Foo',
                        'bar',
                    ],
                ],
            ],
        ]);
    }

    public function resolve($root, array $args): array
    {
        return $args;
    }
}
