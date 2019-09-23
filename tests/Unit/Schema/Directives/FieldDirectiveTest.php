<?php

namespace Tests\Unit\Schema\Directives;

use Tests\TestCase;
use Tests\Utils\Queries\FooBar;
use Nuwave\Lighthouse\Exceptions\DefinitionException;

class FieldDirectiveTest extends TestCase
{
    public function testAssignsResolverFromCombinedDefinition(): void
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

    public function testAssignsResolverWithInvokableClass(): void
    {
        $this->schema = '
        type Query {
            baz: String! @field(resolver:"Tests\\\Utils\\\Resolvers\\\Foo")
        }
        ';

        $this->graphQL('
        {
            baz
        }
        ')->assertJson([
            'data' => [
                'baz' => 'foo.baz',
            ],
        ]);
    }

    public function testCanResolveFieldWithMergedArgs(): void
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

    public function testUsesDefaultFieldNamespace(): void
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

    public function testUsesDefaultFieldNamespaceForInvokableClass(): void
    {
        $this->schema = '
        type Query {
            baz: String! @field(resolver: "FooBar")
        }
        ';

        $this->graphQL('
        {
            baz
        }
        ')->assertJson([
            'data' => [
                'baz' => FooBar::INVOKE_RESULT,
            ],
        ]);
    }

    public function testThrowsAnErrorWhenNoClassFound(): void
    {
        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage("No class 'bar' was found for directive 'field'");

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

    public function testThrowsAnErrorWhenClassIsntInvokable(): void
    {
        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage("Method '__invoke' does not exist on class 'Tests\Utils\Queries\Foo'");

        $this->schema = '
        type Query {
            bar: String! @field(resolver: "Foo")
        }
        ';

        $this->graphQL('
        {
            bar
        }
        ');
    }
}
