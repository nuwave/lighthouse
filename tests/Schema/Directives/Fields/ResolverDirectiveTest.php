<?php

namespace Nuwave\Lighthouse\Tests\Schema\Directives\Fields;

use Nuwave\Lighthouse\Tests\TestCase;

class ResolverDirectiveTest extends TestCase
{
    /**
     * @test
     * @group failing
     */
    public function itCanResolveFieldWithAssignedClass()
    {
        $schema = '
        type Foo {
            bar: String! @resolver(class:"Nuwave\\\Lighthouse\\\Tests\\\Utils\\\Resolvers\\\Foo" method: "bar")
        }
        ';

        $type = schema()->register($schema)->first();
        $fields = $type->config['fields']();
        $resolve = array_get($fields, 'bar.resolve');

        $this->assertEquals('foo.bar', $resolve(null, []));
    }
}
