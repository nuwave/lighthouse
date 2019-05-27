<?php

namespace Tests\Unit\Schema\Directives;

use Tests\TestCase;
use Illuminate\Support\Arr;

class MethodDirectiveTest extends TestCase
{
    protected $schema = '
    type Query {
        foo: Foo @field(resolver: "Tests\\\Unit\\\Schema\\\Directives\\\MethodDirectiveTest@resolve")
    }
    
    type Foo {
        bar(baz: String): String! @method(name: "foobar")
    }
    ';

    /**
     * @test
     */
    public function itWillCallAMethodToResolveField(): void
    {
        $this->graphQL('
        {
            foo {
                bar
            }
        }
        ')->assertJson([
            'data' => [
                'foo' => [
                    'bar' => 'foo',
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function itWillCallAMethodWithArgsToResolveField(): void
    {
        $this->graphQL('
        {
            foo {
                bar(baz: "asdf")
            }
        }
        ')->assertJson([
            'data' => [
                'foo' => [
                    'bar' => 'fooasdf',
                ],
            ],
        ]);
    }

    public function resolve(): Foo
    {
        return new Foo;
    }
}

class Foo
{
    public function foobar($root, array $args = []): string
    {
        return 'foo'.Arr::get($args, 'baz');
    }
}
