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
        $schema = $this->buildSchemaWithDefaultQuery('
            type Foo {
                bar: String! @field(class:"Tests\\\Utils\\\Resolvers\\\Foo" method: "bar")
            }
        ');

        $type = $schema->getType('Foo');
        $fields = $type->config['fields']();
        $resolve = array_get($fields, 'bar.resolve');

        $this->assertEquals('foo.bar', $resolve(null, []));
    }

    /**
     * @test
     */
    public function itCanResolveFieldWithMergedArgs()
    {
        $schema = $this->buildSchemaWithDefaultQuery('
            type Foo {
                bar: String! @field(class:"Tests\\\Utils\\\Resolvers\\\Foo" method: "baz" args:["foo.baz"])
            }
        ');

        $type = $schema->getType('Foo');
        $fields = $type->config['fields']();
        $resolve = array_get($fields, 'bar.resolve');

        $this->assertEquals('foo.baz', $resolve(null, []));
    }

    /**
     * @test
     */
    public function itThrowsAnErrorIfNoClassIsDefined()
    {
        $this->expectException(DirectiveException::class);

        $schema = $this->buildSchemaWithDefaultQuery('
            type Foo {
                bar: String! @field(method: "bar")
            }
        ');
        $type = $schema->getType('Foo');
        $type->config['fields']();
    }

    /**
     * @test
     */
    public function itThrowsAnErrorIfNoMethodIsDefined()
    {
        $this->expectException(DirectiveException::class);

        $schema = $this->buildSchemaWithDefaultQuery('
            type Foo {
                bar: String! @field(class: "Foo\\\Bar")
            }
        ');
        $type = $schema->getType('Foo');
        $type->config['fields']();
    }
}
