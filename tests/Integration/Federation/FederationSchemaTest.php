<?php declare(strict_types=1);

namespace Tests\Integration\Federation;

use GraphQL\Type\Definition\UnionType;
use Nuwave\Lighthouse\Federation\FederationServiceProvider;
use Tests\TestCase;

final class FederationSchemaTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return array_merge(
            parent::getPackageProviders($app),
            [FederationServiceProvider::class],
        );
    }

    public function testServiceQueryShouldReturnValidSdl(): void
    {
        $typeFoo = /** @lang GraphQL */ <<<'GRAPHQL'
type Foo @key(fields: "id") {
  id: ID! @external
  foo: String!
}

GRAPHQL;

        $typeQuery = /** @lang GraphQL */ <<<'GRAPHQL'
type Query {
  foo: Int!
}

GRAPHQL;

        $this->schema = $typeFoo . $typeQuery;

        $sdl = $this->_serviceSdl();

        $this->assertStringContainsString($typeFoo, $sdl);
        $this->assertStringContainsString($typeQuery, $sdl);
    }

    public function testServiceQueryShouldReturnValidSdlWithoutQuery(): void
    {
        $typeFoo = /** @lang GraphQL */ <<<'GRAPHQL'
type Foo @key(fields: "id") {
  id: ID! @external
  foo: String!
}

GRAPHQL;

        $this->schema = $typeFoo;

        $sdl = $this->_serviceSdl();

        $this->assertStringContainsString($typeFoo, $sdl);
        $this->assertStringNotContainsString(/** @lang GraphQL */ 'type Query', $sdl);
    }

    public function testFederatedSchemaShouldContainCorrectEntityUnion(): void
    {
        $schema = $this->buildSchema(/** @lang GraphQL */ '
        type Foo @key(fields: "id") {
          id: ID! @external
          foo: String!
        }

        type Bar @key(fields: "id") {
          id: ID! @external
          bar: String!
        }

        type Query {
          foo: Int!
        }
        ');

        $_Entity = $schema->getType('_Entity');
        $this->assertInstanceOf(UnionType::class, $_Entity);

        $types = $_Entity->getTypes();
        $this->assertSame('Foo', $types[0]->name);
        $this->assertSame('Bar', $types[1]->name);
    }

    public function testServiceQueryShouldReturnFederationV2SchemaExtension(): void
    {
        $schemaExtension = /** @lang GraphQL */ <<<'GRAPHQL'
extend schema @link(url: "https:\/\/specs.apollo.dev\/federation\/v2.3", import: ["@composeDirective", "@extends", "@external", "@inaccessible", "@interfaceObject", "@key", "@override", "@provides", "@requires", "@shareable", "@tag"])
GRAPHQL;

        $typeFoo = /** @lang GraphQL */ <<<'GRAPHQL'
type Foo @key(fields: "id") {
  id: ID!
}
GRAPHQL;

        $this->schema = "{$schemaExtension} {$typeFoo}";

        $sdl = $this->_serviceSdl();

        $this->assertStringContainsString($schemaExtension, $sdl);
        $this->assertStringContainsString($typeFoo, $sdl);
    }

    public function testServiceQueryShouldReturnFederationV2ComposedDirectives(): void
    {
        $schemaExtension = /** @lang GraphQL */ <<<'GRAPHQL'
extend schema @link(url: "https:\/\/specs.apollo.dev\/federation\/v2.3", import: ["@composeDirective"]) @link(url: "https:\/\/myspecs.dev\/myCustomDirective\/v1.0", import: ["@foo", "@bar"]) @composeDirective(name: "@foo") @composeDirective(name: "@bar")
GRAPHQL;

        $typeFoo = /** @lang GraphQL */ <<<'GRAPHQL'
type Foo @key(fields: "id") {
  id: ID!
}
GRAPHQL;

        $this->schema = "{$schemaExtension} {$typeFoo}";

        $sdl = $this->_serviceSdl();

        $this->assertStringContainsString($schemaExtension, $sdl);
        $this->assertStringContainsString('directive @foo on FIELD_DEFINITION', $sdl);
        $this->assertStringContainsString('directive @bar on FIELD_DEFINITION', $sdl);
        $this->assertStringContainsString($typeFoo, $sdl);
    }

    protected function _serviceSdl(): string
    {
        $response = $this->graphQL(/** @lang GraphQL */ '
        {
            _service {
                sdl
            }
        }
        ');

        return $response->json('data._service.sdl');
    }
}
