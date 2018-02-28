<?php

namespace Nuwave\Lighthouse\Tests\Schema\Directives\Fields;

use Nuwave\Lighthouse\Tests\TestCase;

class CanDirectiveTest extends TestCase
{
    /**
     * @test
     */
    public function itCanAttachPoliciesToField()
    {
        $schema = '
        type Foo {
            bar: String! @can(if: ["viewBar"])
        }
        ';

        $type = schema()->register($schema)->first();
        $fields = $type->config['fields']();
        $resolver = array_get($fields, 'bar.resolve');
        // TODO: Use prophecy to ensure middleware is called
        $this->assertTrue(true);
        // dd($resolver(['bar' => 'baz'], []));
    }
}
