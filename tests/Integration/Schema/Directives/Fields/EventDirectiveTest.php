<?php

namespace Tests\Integration\Schema\Directives\Fields;

use Tests\DBTestCase;
use Illuminate\Support\Facades\Event;
use Tests\Integration\Schema\Directives\Fields\Fixtures\CompanyWasCreatedEvent;

class EventDirectiveTest extends DBTestCase
{
    /**
     * @test
     */
    public function itDispatchesAnEventWithDispatch(): void
    {
        Event::fake([
            CompanyWasCreatedEvent::class,
        ]);

        $this->schema = '
        type Company {
            id: ID!
            name: String!
        }
        
        type Mutation {
            createCompany(name: String): Company @create
                @event(dispatch: "Tests\\\\Integration\\\\Schema\\\\Directives\\\\Fields\\\\Fixtures\\\\CompanyWasCreatedEvent")
        }
        '.$this->placeholderQuery();

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

    /**
     * @test
     */
    public function itDispatchesAnEventWithFire(): void
    {
        Event::fake([
            CompanyWasCreatedEvent::class,
        ]);

        $this->schema = '
        type Company {
            id: ID!
            name: String!
        }
        
        type Mutation {
            createCompany(name: String): Company @create
                @event(fire: "Tests\\\\Integration\\\\Schema\\\\Directives\\\\Fields\\\\Fixtures\\\\CompanyWasCreatedEvent")
        }
        '.$this->placeholderQuery();

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

    /**
     * @test
     */
    public function itDispatchesAnEventWithClass(): void
    {
        Event::fake([
            CompanyWasCreatedEvent::class,
        ]);

        $this->schema = '
        type Company {
            id: ID!
            name: String!
        }
        
        type Mutation {
            createCompany(name: String): Company @create
                @event(class: "Tests\\\\Integration\\\\Schema\\\\Directives\\\\Fields\\\\Fixtures\\\\CompanyWasCreatedEvent")
        }
        '.$this->placeholderQuery();

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
