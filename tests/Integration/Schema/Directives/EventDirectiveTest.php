<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Company;
use Illuminate\Support\Facades\Event;

class EventDirectiveTest extends DBTestCase
{
    public function eventDirectiveArgumentAliases(): array
    {
        return [
            ['dispatch'],
            /*
             * @deprecated The aliases for dispatch will be removed in v4
             */
            ['fire'],
            ['class'],
        ];
    }

    /**
     * @dataProvider eventDirectiveArgumentAliases
     * @param  string  $argumentName
     * @test
     */
    public function itDispatchesAnEvent(string $argumentName): void
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
                @event('.$argumentName.': "Tests\\\\Integration\\\\Schema\\\\Directives\\\\Fields\\\\Fixtures\\\\CompanyWasCreatedEvent")
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

class CompanyWasCreatedEvent
{
    /**
     * @var \Tests\Utils\Models\Company
     */
    public $company;

    public function __construct(Company $company)
    {
        $this->company = $company;
    }
}
