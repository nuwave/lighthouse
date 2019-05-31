<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Company;
use Illuminate\Support\Facades\Event;

class EventDirectiveTest extends DBTestCase
{
    /**
     * @test
     */
    public function itDispatchesAnEvent(): void
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
                @event(dispatch: "Tests\\\\Integration\\\\Schema\\\\Directives\\\\CompanyWasCreatedEvent")
        }
        '.$this->placeholderQuery();

        $this->graphQL('
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
            return $event->company->id === 1
                && $event->company->name === 'foo';
        });
    }
}

class CompanyWasCreatedEvent
{
    /**
     * @var \Tests\Utils\Models\Company
     */
    public $company;

    /**
     * CompanyWasCreatedEvent constructor.
     * @param  \Tests\Utils\Models\Company  $company
     * @return void
     */
    public function __construct(Company $company)
    {
        $this->company = $company;
    }
}
