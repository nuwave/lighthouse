<?php

namespace Tests\Integration\Federation;

use GraphQL\Type\Definition\UnionType;
use Nuwave\Lighthouse\Federation\FederationServiceProvider;
use Tests\TestCase;

class FederationSchemaTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return array_merge(
            parent::getPackageProviders($app),
            [FederationServiceProvider::class]
        );
    }

    public function testServiceQueryShouldReturnValidSdl(): void
    {
        $foo = /** @lang GraphQL */ <<<'GRAPHQL'
type Foo @key(fields: "id") {
  id: ID! @external
  foo: String!
}

GRAPHQL;

        $query = /** @lang GraphQL */ <<<'GRAPHQL'
type Query {
  foo: Int!
}

GRAPHQL;

        $this->schema = $foo.$query;

        $sdl = $this->_serviceSdl();

        $this->assertStringContainsString($foo, $sdl);
        $this->assertStringContainsString($query, $sdl);
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

        /** @var \GraphQL\Type\Definition\UnionType|null $_Entity */
        $_Entity = $schema->getType('_Entity');
        $this->assertInstanceOf(UnionType::class, $_Entity);

        $types = $_Entity->getTypes();
        $this->assertSame('Foo', $types[0]->name);
        $this->assertSame('Bar', $types[1]->name);
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
