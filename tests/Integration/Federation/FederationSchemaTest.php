<?php

namespace Tests\Integration\Federation;

use Nuwave\Lighthouse\Federation\FederationServiceProvider;
use Tests\DBTestCase;

class FederationSchemaTest extends DBTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function getPackageProviders($app)
    {
        return array_merge(
            parent::getPackageProviders($app),
            [FederationServiceProvider::class]
        );
    }

    public function testServiceQueryShouldReturnValidSdl()
    {
        $this->schema = '
        type Foo @key(fields: "id") {
            id: ID! @external
            foo: String!
        }
        type Query {
            foo: Int!
        }
        ';

        $this->graphQL('
            {
                _service { sdl }
            }
        ')
            ->assertJson([
                'data' => [
                    '_service' => [
                        'sdl' => $this->schema,
                    ],
                ],
            ]);
    }

    public function testFederatedSchemaShouldContainCorrectEntityUnion()
    {
        // TODO introspect the schema and validate that the _Entity union contains all the types which we defined in the
        // schema within this test case
    }
}
