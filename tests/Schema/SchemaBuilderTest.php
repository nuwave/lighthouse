<?php

namespace Nuwave\Lighthouse\Tests\Schema;

use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InterfaceType;
use Nuwave\Lighthouse\Tests\TestCase;

class SchemaBuilderTest extends TestCase
{
    /**
     * @test
     */
    public function itCanResolveEnumTypes()
    {
        $schema = '
        enum Role {
            # Company administrator.
            admin @enum(value:"admin")

            # Company employee.
            employee @enum(value:"employee")
        }
        ';

        $types = schema()->register($schema);
        $this->assertInstanceOf(EnumType::class, $types->first());
    }

    /**
     * @test
     */
    public function itCanResolveInterfaceTypes()
    {
        $schema = '
        interface Foo {
            # bar is baz
            bar: String!
        }
        ';

        $types = schema()->register($schema);
        $this->assertInstanceOf(InterfaceType::class, $types->first());
    }
}
