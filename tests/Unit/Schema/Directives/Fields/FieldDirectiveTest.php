<?php

namespace Tests\Unit\Schema\Directives\Fields;

use Tests\TestCase;
use Illuminate\Support\Arr;
use Tests\Utils\Queries\FooBar;
use Nuwave\Lighthouse\Exceptions\DirectiveException;

class FieldDirectiveTest extends TestCase
{
    /**
     * @test
     */
    public function itAssignsResolverFromCombinedDefinition()
    {
        $this->schema = '
        type Query {
            bar: String! @field(resolver:"Tests\\\Utils\\\Resolvers\\\Foo@bar")
        }
        ';
        $query = '
        {
            bar
        }        
        ';

        $this->query($query)->assertJson([
            'data' => [
                'bar' => 'foo.bar'
            ]
        ]);
    }

    /**
     * @test
     */
    public function itCanResolveFieldWithMergedArgs()
    {
        $this->schema = '
        type Query {
            bar: String! @field(resolver: "Tests\\\Utils\\\Resolvers\\\Foo@baz" args:["foo.baz"])
        }
        ';
        $query = '
        {
            bar
        }        
        ';

        $this->query($query)->assertJson([
            'data' => [
                'bar' => 'foo.baz'
            ]
        ]);
    }

    /**
     * @test
     */
    public function itUsesDefaultFieldNamespace()
    {
        $this->schema = '
        type Query {
            bar: String! @field(resolver: "FooBar@customResolve")
        }
        ';
        $query = '
        {
            bar
        }        
        ';

        $this->query($query)->assertJson([
            'data' => [
                'bar' => FooBar::CUSTOM_RESOLVE_RESULT
            ]
        ]);
    }

    /**
     * @test
     */
    public function itThrowsAnErrorOnlyOnePartIsDefined()
    {
        $this->expectException(DirectiveException::class);
        $this->schema = '
        type Query {
            bar: String! @field(resolver: "bar")
        }
        ';
        $query = '
        {
            bar
        }        
        ';
        $this->query($query);
    }
}
