<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Podcast;

final class WithoutGlobalScopesDirectiveTest extends DBTestCase
{
    public function testDefaultCondition(): void
    {
        factory(Podcast::class)->create();

        $this->schema = /** @lang GraphQL */
            '
        type Query {
             podcasts(
        allPodcasts: Boolean @withoutGlobalScopes(names: ["published"])
        )

    : [Podcast!]! @all
        }

        type Podcast {
            id: ID!
            schedule_at: String!

        }
        ';


        $this->graphQL(/** @lang GraphQL */ '
        {
            podcasts {
                id
                schedule_at
            }
        }
        ')->assertExactJson(
            [
                'data' => [
                    'podcasts' => [],
                ],
            ]
        );
    }

    public function testWithPassingDirective(): void
    {
        $podcast = factory(Podcast::class)->create();

        $this->schema = /** @lang GraphQL */
            '
        type Query {
             podcasts(
        allPodcasts: Boolean @withoutGlobalScopes(names: ["published"])
        )

    : [Podcast!]! @all
        }

        type Podcast {
            id: ID!

        }
        ';


        $this->graphQL(/** @lang GraphQL */ '
        {
            podcasts (allPodcasts: true) {
                id
            }
        }
        ')->assertExactJson(
            [
                'data' => [
                    'podcasts' => [
                        [
                            'id' => "{$podcast->id}",
                        ],
                    ],
                ],
            ]
        );
    }


}
