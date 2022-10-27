<?php

namespace Tests\Integration\ModelNotFound;

use Tests\DBTestCase;

/**
 * This test out the functionality of the error handler `NotFoundErrorHandler`.
 */
class ModelNotFoundTest extends DBTestCase
{
    protected $schema = /** @lang GraphQL */
        '
    type Post {
        id: ID!
    }

    type Query {
            post (
                id: ID! @eq
            ): Post @find @can(ability: "view", find: "id")
        }
    ';

    public function testModelNotFoundWithCanDirective()
    {
        $this->graphQL(
        /** @lang GraphQL */ '
        {
            post(id: -1) {
                id
            }
        }
        '
        )
            ->assertGraphQLErrorMessage( 'No query results for model [Tests\Utils\Models\Post] -1')
            ->assertGraphQLErrorCategory('graphql');
    }
}
