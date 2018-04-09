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
        $schema = '
        type Foo {
            fooBar: String! @rename(attribute: "foo_bar")
        }
        ';

        $type = schema()->register($schema)->first();
        $fields = $type->config['fields']();
        $resolver = array_get($fields, 'fooBar.resolve');
        $this->assertEquals('bar', $resolver(['foo_bar' => 'bar', 'fooBar' => 'baz'], []));
    }

    /**
     * @test
     */
    public function itThrowsAnExceptionIfNoAttributeDefined()
    {
        $schema = '
        type Foo {
            fooBar: String! @rename
        }
        ';

        $this->expectException(DirectiveException::class);
        $type = schema()->register($schema)->first();
        $type->config['fields']();
    }
}
