<?php declare(strict_types=1);

namespace Tests\Unit\Schema;

use GraphQL\Language\DirectiveLocation;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\TypeKind;
use Tests\TestCase;

final class ClientDirectiveTest extends TestCase
{
    public function testReturnsDefaultDirectivesInIntrospection(): void
    {
        $this->assertNotNull(
            $this->introspectDirective(Directive::SKIP_NAME),
        );
        $this->assertNotNull(
            $this->introspectDirective(Directive::INCLUDE_NAME),
        );
    }

    public function testDefineACustomClientDirective(): void
    {
        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        "foo"
        directive @bar(
            "foobar"
            baz: String = "barbaz"
        ) on FIELD
        GRAPHQL;

        $introspection = $this->introspectDirective('bar');

        $this->assertIsArray($introspection);
        $this->assertSame('bar', $introspection['name']);
        $this->assertSame('foo', $introspection['description']);

        $args = $introspection['args'];
        $this->assertIsArray($args);
        $this->assertCount(1, $args);

        [$argBaz] = $args;
        $this->assertIsArray($argBaz);
        $this->assertSame('baz', $argBaz['name']);
        $this->assertSame('foobar', $argBaz['description']);
        $this->assertSame('"barbaz"', $argBaz['defaultValue']);

        $argBazType = $argBaz['type'];
        $this->assertIsArray($argBazType);
        $this->assertSame(TypeKind::SCALAR, $argBazType['kind']);
        $this->assertSame(Type::STRING, $argBazType['name']);
        $this->assertNull($argBazType['ofType']);

        $this->assertSame([DirectiveLocation::FIELD], $introspection['locations']);
    }
}
