<?php

namespace Tests\Unit\Schema\Directives\Fields;

use Tests\TestCase;
use Nuwave\Lighthouse\Exceptions\DirectiveException;

class FieldDirectiveTest extends TestCase
{
    /**
     * @test
     */
    public function itCanResolveFieldWithAssignedClass()
    {
        $schema = '
        type Query {
            bar: String! @field(class:"Tests\\\Utils\\\Resolvers\\\Foo" method: "bar")
        }
        ';
        $query = '
        {
            bar
        }        
        ';
        $result = $this->execute($schema, $query);

        $this->assertEquals('foo.bar', array_get($result, 'data.bar'));
    }

    /**
     * @test
     */
    public function itAssignsResolverFromCombinedDefinition()
    {
        $schema = '
        type Query {
            bar: String! @field(resolver:"Tests\\\Utils\\\Resolvers\\\Foo@bar")
        }
        ';
        $query = '
        {
            bar
        }        
        ';
        $result = $this->execute($schema, $query);

        $this->assertEquals('foo.bar', array_get($result, 'data.bar'));
    }

    /**
     * @test
     */
    public function itCanResolveFieldWithMergedArgs()
    {
        $schema = '
        type Query {
            bar: String! @field(class:"Tests\\\Utils\\\Resolvers\\\Foo" method: "baz" args:["foo.baz"])
        }
        ';
        $query = '
        {
            bar
        }        
        ';
        $result = $this->execute($schema, $query);

        $this->assertEquals('foo.baz', array_get($result, 'data.bar'));
    }

    /**
     * @test
     */
    public function itThrowsAnErrorIfNoClassIsDefined()
    {
        $this->expectException(DirectiveException::class);
        $schema = '
        type Query {
            bar: String! @field(method: "bar")
        }
        ';
        $query = '
        {
            bar
        }        
        ';
        $this->execute($schema, $query);
    }

    /**
     * @test
     */
    public function itThrowsAnErrorIfNoMethodIsDefined()
    {
        $this->expectException(DirectiveException::class);
        $schema = '
        type Query {
            bar: String! @field(class: "Foo\\\Bar")
        }
        ';
        $query = '
        {
            bar
        }        
        ';
        $this->execute($schema, $query);
    }
}
