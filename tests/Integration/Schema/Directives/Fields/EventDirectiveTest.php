<?php

namespace Tests\Integration\Schema\Directives\Fields;

use Tests\DBTestCase;
use Illuminate\Support\Facades\Event;
use Tests\Integration\Schema\Directives\Fields\Fixtures\CompanyWasCreatedEvent;

class EventDirectiveTest extends DBTestCase
{
    public function providerForItDispatchesAnEvent(): array
    {
        return [
            ['dispatch'],
            ['fire'],
            ['class'],
        ];
    }

    /**
     * @dataProvider providerForItDispatchesAnEvent
     * @param  string  $method
     * @test
     */
    public function itDispatchesAnEvent(string $method): void
    {
        Event::fake([
            CompanyWasCreatedEvent::class,
        ]);

        $this->schema = sprintf('
        type Company {
            id: ID!
            name: String!
        }
        
        type Mutation {
            createCompany(name: String): Company @create
                @event(%s: "Tests\\\\Integration\\\\Schema\\\\Directives\\\\Fields\\\\Fixtures\\\\CompanyWasCreatedEvent")
        }
        ', $method).$this->placeholderQuery();

        $this->query('
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

        Event::assertDispatched(CompanyWasCreatedEvent::class, function ($event) {
            return $event->company->id === 1 && $event->company->name === 'foo';
        });
    }
}
