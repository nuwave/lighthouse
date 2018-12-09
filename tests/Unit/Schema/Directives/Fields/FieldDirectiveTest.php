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

        $this->assertSame('foo.bar', Arr::get($result, 'data.bar'));
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

        $this->assertSame('foo.baz', Arr::get($result, 'data.bar'));
    }

    /**
     * @test
     */
    public function itUsesDefaultFieldNamespace()
    {
        $schema = '
        type Query {
            bar: String! @field(resolver: "FooBar@customResolve")
        }
        ';
        $query = '
        {
            bar
        }        
        ';
        $result = $this->execute($schema, $query);

        $this->assertSame(FooBar::CUSTOM_RESOLVE_RESULT, Arr::get($result, 'data.bar'));
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
}
