<?php

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

        $this->schema .= /** @lang GraphQL */ '
        type Company {
            id: ID!
            name: String!
        }

        type Mutation {
            createCompany(name: String): Company @create
                @event(dispatch: "Tests\\\\Integration\\\\Schema\\\\Directives\\\\Fixtures\\\\CompanyWasCreatedEvent")
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            createCompany(name: "foo") {
                id
                name
            }
        }
        ')->assertJson([
            'data' => [
                'createCompany' => [
                    'id' => '1',
                    'name' => 'foo',
                ],
            ],
        ]);

        Event::assertDispatched(CompanyWasCreatedEvent::class, function ($event): bool {
            return 1 === $event->company->id
                && 'foo' === $event->company->name;
        });
    }
}
