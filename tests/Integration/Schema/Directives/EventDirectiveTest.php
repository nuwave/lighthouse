<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Illuminate\Support\Facades\Event;
use Tests\DBTestCase;
use Tests\Integration\Schema\Directives\Fixtures\CompanyWasCreatedEvent;

final class EventDirectiveTest extends DBTestCase
{
    public function testDispatchesAnEvent(): void
    {
        Event::fake([
            CompanyWasCreatedEvent::class,
        ]);

        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Company {
            id: ID!
            name: String!
        }

        type Mutation {
            createCompany(name: String): Company @create
                @event(dispatch: "Tests\\Integration\\Schema\\Directives\\Fixtures\\CompanyWasCreatedEvent")
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            createCompany(name: "foo") {
                id
                name
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'createCompany' => [
                    'id' => '1',
                    'name' => 'foo',
                ],
            ],
        ]);

        Event::assertDispatched(CompanyWasCreatedEvent::class, static fn ($event): bool => $event->company->id === 1
            && $event->company->name === 'foo');
    }
}
