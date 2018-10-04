<?php

namespace Tests\Unit\Schema\Directives\Fields;

use Tests\TestCase;
use Nuwave\Lighthouse\Exceptions\DirectiveException;

class FieldDirectiveTest extends TestCase
{
    /**
     * @test
     * @deprecated this option of defining field resolvers will be removed in v3
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
            bar: String! @field(resolver: "Tests\\\Utils\\\Resolvers\\\Foo@baz" args:["foo.baz"])
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
    public function itThrowsAnErrorOnlyOnePartIsDefined()
    {
        $this->expectException(DirectiveException::class);
        $schema = '
        type Query {
            bar: String! @field(resolver: "bar")
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
    public function itThrowsAnErrorIfOnePartIsEmpty()
    {
        $this->expectException(DirectiveException::class);
        $schema = '
        type Query {
            bar: String! @field(class: "Foo\\\Bar@")
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
