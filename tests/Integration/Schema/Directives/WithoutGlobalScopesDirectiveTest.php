<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Episode;

final class WithoutGlobalScopesDirectiveTest extends DBTestCase
{
    public function testDefaultCondition(): void
    {
        factory(Episode::class)->make();

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

        }
        ';


        $this->graphQL(/** @lang GraphQL */ '
        {
            episodes {
                id
            }
        }
        ')->assertExactJson(
            [
                'data' => [
                    'episodes' => [],
                ],
            ]
        );
    }

    public function testWithPassingDirective(): void
    {
        $episode = factory(Episode::class)->make();

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

        }
        ';


        $this->graphQL(/** @lang GraphQL */ '
        {
            episodes (allEpisodes: true) {
                id
            }
        }
        ')->assertExactJson(
            [
                'data' => [
                    'episodes' => [
                        [
                            'id' => "{$episode->id}",
                        ],
                    ],
                ],
            ]
        );
    }


}
