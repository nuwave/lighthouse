<?php

namespace Tests\Unit\Schema\Directives;

use Tests\TestCase;
use Tests\Utils\Queries\FooBar;
use Nuwave\Lighthouse\Exceptions\DirectiveException;

class FieldDirectiveTest extends TestCase
{
    /**
     * @test
     */
    public function itAssignsResolverFromCombinedDefinition(): void
    {
        $this->schema = '
        type Query {
            bar: String! @field(resolver:"Tests\\\Utils\\\Resolvers\\\Foo@bar")
        }
        ';

        $this->graphQL('
        {
            bar
        }        
        ')->assertJson([
            'data' => [
                'bar' => 'foo.bar',
            ],
        ]);
    }

    /**
     * @test
     */
    public function itCanResolveFieldWithMergedArgs(): void
    {
        $this->schema = '
        type Query {
            bar: String! @field(resolver: "Tests\\\Utils\\\Resolvers\\\Foo@baz" args:["foo.baz"])
        }
        ';

        $this->graphQL('
        {
            bar
        }        
        ')->assertJson([
            'data' => [
                'bar' => 'foo.baz',
            ],
        ]);
    }

    /**
     * @test
     */
    public function itUsesDefaultFieldNamespace(): void
    {
        $this->schema = '
        type Query {
            bar: String! @field(resolver: "FooBar@customResolve")
        }
        ';

        $this->graphQL('
        {
            bar
        }        
        ')->assertJson([
            'data' => [
                'bar' => FooBar::CUSTOM_RESOLVE_RESULT,
            ],
        ]);
    }

    /**
     * @test
     */
    public function itThrowsAnErrorOnlyOnePartIsDefined(): void
    {
        $this->expectException(DirectiveException::class);

        $this->schema = '
        type Query {
            bar: String! @field(resolver: "bar")
        }
        ';

        $this->graphQL('
        {
            bar
        }        
        ');
    }
}
