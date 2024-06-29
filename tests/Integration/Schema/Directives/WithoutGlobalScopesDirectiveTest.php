<?php

namespace Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Episode;

class WithoutGlobalScopesDirectiveTest extends DBTestCase
{
    public function testWithScope(): void
    {
        factory(Episode::class)->times(10)->make();

        $this->schema = /** @lang GraphQL */
            '
        type Query {
             episodes(
        allEpisodes: Boolean @withoutGlobalScopes(names: ["published"])
        )

    : [Episode!]! @all
        }

        type Episode {
            id: ID!
            title: String!
            schedule_at : DateTime
        }
        ';


        $this->graphQL(/** @lang GraphQL */ '
        {
            episodes {
                id
            }
        }
        ')->assertJsonCount(0, 'data.episodes');
    }

    public function testWithoutScope(): void
    {
        factory(Episode::class)->times(10)->make();

        $this->schema = /** @lang GraphQL */
            '
        type Query {
             episodes(
        allEpisodes: Boolean @withoutGlobalScopes(names: ["published"])
        )

    : [Episode!]! @all
        }

        type Episode {
            id: ID!
            title: String!
            schedule_at : DateTime
        }
        ';


        $this->graphQL(/** @lang GraphQL */ '
        {
            episodes (allEpisodes: true) {
                id
            }
        }
        ')->assertJsonCount(10, 'data.episodes');
    }
}
