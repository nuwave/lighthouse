<?php

namespace Tests\Unit\Schema\Directives\Fields;

use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Tests\TestCase;

class RenameDirectiveTest extends TestCase
{
    /**
     * @test
     */
    public function itCanRenameAField()
    {
        $schema = $this->buildSchemaWithDefaultQuery('
            type Foo {
                fooBar: String! @rename(attribute: "foo_bar")
            }
        ');

        $type = $schema->getType('Foo');
        $fields = $type->config['fields']();
        $resolver = array_get($fields, 'fooBar.resolve');
        $this->assertEquals('bar', $resolver(['foo_bar' => 'bar', 'fooBar' => 'baz'], []));
    }

    /**
     * @test
     */
    public function itThrowsAnExceptionIfNoAttributeDefined()
    {
        $this->expectException(DirectiveException::class);

        $schema = $this->buildSchemaWithDefaultQuery('
            type Foo {
                fooBar: String! @rename
            }
        ');

        $type = $schema->getType('Foo');
        $type->config['fields']();
    }
}
