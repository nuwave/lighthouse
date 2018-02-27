<?php

namespace Nuwave\Lighthouse\Tests\Schema\Directives\Fields;

use Nuwave\Lighthouse\Tests\TestCase;

class MethodDirectiveTest extends TestCase
{
    /**
     * @test
     */
    public function itWillCallAMethodToResolveField()
    {
        $root = new class() {
            public function foobar()
            {
                return 'baz';
            }
        };

        $schema = '
        type Foo {
            bar: String! @method(name: "foobar")
        }
        ';

        $type = schema()->register($schema)->first();
        $fields = $type->config['fields']();
        $resolver = array_get($fields, 'bar.resolve');
        $this->assertEquals('baz', $resolver($root, []));
    }

    /**
     * @test
     */
    public function itWillCallAMethodWithArgsToResolveField()
    {
        $root = new class() {
            public function bar(array $args)
            {
                return array_get($args, 'baz');
            }
        };

        $schema = '
        type Foo {
            bar(baz: String!): String! @method(name: "bar")
        }
        ';

        $type = schema()->register($schema)->first();
        $fields = $type->config['fields']();
        $resolver = array_get($fields, 'bar.resolve');
        $this->assertEquals('foo', $resolver($root, ['baz' => 'foo']));
    }
}
