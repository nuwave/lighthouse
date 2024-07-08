<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Podcast;

final class WithoutGlobalScopesDirectiveTest extends DBTestCase
{
    public function testDefaultCondition(): void
    {
        factory(Podcast::class, 5)->create();

        $scheduled_podcasts = Podcast::query()
            ->get();

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
                    'podcasts' => $scheduled_podcasts
                        ->map(static fn (Podcast $podcast): array => [
                            'id' => (string) $podcast->id,
                            'schedule_at' => $podcast->schedule_at,
                        ])
                ]
            ]
        )->assertJsonCount($scheduled_podcasts->count(), 'data.podcasts');
    }

    public function testWithPassingDirective(): void
    {
        factory(Podcast::class, 5)->create();

        $all_podcasts = Podcast::query()
            ->withoutGlobalScopes(["published"])
            ->get();

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
            schedule_at: String
        }
        ';


        $this->graphQL(/** @lang GraphQL */ '
        {
            podcasts (allPodcasts: true) {
                id
                schedule_at
            }
        }
        ')->assertExactJson(
            [
                'data' => [
                    'podcasts' => [
                        [
                            'id' => (string) $all_podcasts[0]->id,
                            'schedule_at' => $all_podcasts[0]->schedule_at ?? null
                        ],
                        [
                            'id' => (string) $all_podcasts[1]->id,
                            'schedule_at' => $all_podcasts[1]->schedule_at ?? null
                        ],
                        [
                            'id' => (string) $all_podcasts[2]->id,
                            'schedule_at' => $all_podcasts[2]->schedule_at ?? null
                        ],
                        [
                            'id' => (string) $all_podcasts[3]->id,
                            'schedule_at' => $all_podcasts[3]->schedule_at ?? null
                        ],
                        [
                            'id' => (string) $all_podcasts[4]->id,
                            'schedule_at' => $all_podcasts[4]->schedule_at ?? null
                        ],
                    ],
                ],
            ]
        )->assertJsonCount($all_podcasts->count(), 'data.podcasts');
    }
}
