<?php

namespace Tests\Unit\Schema\Directives\Fields;

use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Tests\TestCase;

class FieldDirectiveTest extends TestCase
{
    /**
     * @test
     */
    public function itCanResolveFieldWithAssignedClass()
    {
        $schema = '
        type Foo {
            bar: String! @field(class:"Tests\\\Utils\\\Resolvers\\\Foo" method: "bar")
        }
        ';

        $type = schema()->register($schema)->first();
        $fields = $type->config['fields']();
        $resolve = array_get($fields, 'bar.resolve');

        $this->assertEquals('foo.bar', $resolve(null, []));
    }

    /**
     * @test
     */
    public function itCanResolveFieldWithMergedArgs()
    {
        $schema = '
        type Foo {
            bar: String! @field(class:"Tests\\\Utils\\\Resolvers\\\Foo" method: "baz" args:["foo.baz"])
        }
        ';

        $type = schema()->register($schema)->first();
        $fields = $type->config['fields']();
        $resolve = array_get($fields, 'bar.resolve');

        $this->assertEquals('foo.baz', $resolve(null, []));
    }

    /**
     * @test
     */
    public function itThrowsAnErrorIfNoClassIsDefined()
    {
        $schema = '
        type Foo {
            bar: String! @field(method: "bar")
        }
        ';

        $this->expectException(DirectiveException::class);
        $type = schema()->register($schema)->first();
        $type->config['fields']();
    }

    /**
     * @test
     */
    public function itThrowsAnErrorIfNoMethodIsDefined()
    {
        $schema = '
        type Foo {
            bar: String! @field(class: "Foo\\\Bar")
        }
        ';

        $this->expectException(DirectiveException::class);
        $type = schema()->register($schema)->first();
        $type->config['fields']();
    }
}
