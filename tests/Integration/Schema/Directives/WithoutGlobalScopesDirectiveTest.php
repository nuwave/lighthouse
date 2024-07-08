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
        assert($scheduledPodcast instanceof Podcast);
        $scheduledPodcast->schedule_at = Carbon::tomorrow();
        $scheduledPodcast->save();

        $unscheduledPodcast = factory(Podcast::class)->make();
        assert($unscheduledPodcast instanceof Podcast);
        $unscheduledPodcast->schedule_at = null;
        $unscheduledPodcast->save();

        $this->schema = /** @lang GraphQL */ '
        type Query {
            podcasts(
                includeUnscheduled: Boolean @withoutGlobalScopes(names: ["scheduled"])
            ): [Podcast!]! @all
        }

        type Podcast {
            id: ID!
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            podcasts {
                id
            }
        }
        ')->assertJsonCount(1, 'data.podcasts');

        $this->graphQL(/** @lang GraphQL */ '
        {
            podcasts(includeUnscheduled: true) {
                id
            }
        }
        ')->assertJsonCount(2, 'data.podcasts');
    }
}
