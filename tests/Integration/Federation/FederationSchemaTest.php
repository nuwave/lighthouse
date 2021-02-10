<?php

namespace Tests\Integration\Federation;

use Nuwave\Lighthouse\Federation\FederationServiceProvider;
use Tests\DBTestCase;

class FederationSchemaTest extends DBTestCase
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

        $response = $this->graphQL(/** @lang GraphQL */ '
        {
            _service {
                sdl
            }
        }
        ');

        $sdl = $response->json('data._service.sdl');
        $this->assertStringContainsString($foo, $sdl);
        $this->assertStringContainsString($query, $sdl);
    }

    public function testFederatedSchemaShouldContainCorrectEntityUnion(): void
    {
        // TODO introspect the schema and validate that the _Entity union contains all the types which we defined in the
        // schema within this test case
    }
}
