<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Illuminate\Support\Carbon;
use Tests\DBTestCase;
use Tests\Utils\Models\Podcast;

final class WithoutGlobalScopesDirectiveTest extends DBTestCase
{
    public function testOmitsScopesWhenArgumentValueIsTrue(): void
    {
        $scheduledPodcast = factory(Podcast::class)->make();
        $this->assertInstanceOf(Podcast::class, $scheduledPodcast);
        $scheduledPodcast->schedule_at = Carbon::tomorrow();
        $scheduledPodcast->save();

        $unscheduledPodcast = factory(Podcast::class)->make();
        $this->assertInstanceOf(Podcast::class, $unscheduledPodcast);
        $unscheduledPodcast->schedule_at = null;
        $unscheduledPodcast->save();

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            podcasts(
                includeUnscheduled: Boolean @withoutGlobalScopes(names: ["scheduled"])
            ): [Podcast!]! @all
        }

        type Podcast {
            id: ID!
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            podcasts {
                id
            }
        }
        GRAPHQL)->assertJsonCount(1, 'data.podcasts');

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            podcasts(includeUnscheduled: true) {
                id
            }
        }
        GRAPHQL)->assertJsonCount(2, 'data.podcasts');
    }
}
