<?php

namespace Tests\Unit\Schema\Directives\Fields;

use Tests\TestCase;

class MethodDirectiveTest extends TestCase
{
    /**
     * @test
     */
    public function itWillCallAMethodToResolveField()
    {
        $result = $this->execute($this->schema(), '
        {
            foo {
                bar
            }
        }
        ');

        $this->assertSame('foo', \Illuminate\Support\Arr::get($result, 'data.foo.bar'));
    }

    /**
     * @test
     */
    public function itWillCallAMethodWithArgsToResolveField()
    {
        $result = $this->execute($this->schema(), '
        {
            foo {
                bar(baz: "asdf")
            }
        }
        ');

        $this->assertSame('fooasdf', \Illuminate\Support\Arr::get($result, 'data.foo.bar'));
    }

    public function resolve()
    {
        return new Foo;
    }

    protected function schema()
    {
        $resolver = addslashes(self::class).'@resolve';

        return "
        type Query {
            foo: Foo @field(resolver: \"{$resolver}\")
        }
        
        type Foo {
            bar(baz: String): String! @method(name: \"foobar\")
        }
        ";
    }
}

class Foo {
    public function foobar(array $args = []): string
    {
        return 'foo' . \Illuminate\Support\Arr::get($args, 'baz');
    }
}
