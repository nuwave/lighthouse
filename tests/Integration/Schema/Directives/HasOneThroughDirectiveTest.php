<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\Car;
use Tests\Utils\Models\Mechanic;
use Tests\Utils\Models\Owner;


final class HasOneThroughDirectiveTest extends DBTestCase
{
    public function testQueryHasOneThroughRelationship(): void
    {
        $this->schema = /** @lang GraphQL */
            '

         type Query {
         mechanics: [Mechanic]! @all
          }

        type Mechanic {
            id: ID!
            owner: Owner @hasOneThrough
        }

        type Car {
             id: ID!
        }

        type Owner {
               id: ID!
               name: String

        }
        ';

        $mechanic = factory(Mechanic::class)->create();
        assert($mechanic instanceof Mechanic);

        $car = factory(Car::class)->create();
        assert($car instanceof Car);

        $owner = factory(Owner::class)->make();
        assert($owner instanceof Owner);

        $mechanic->car()->save($car);
        $car->owner()->save($owner);

        $mechanic_owner = $mechanic->owner()->first();
        $this->graphQL(/** @lang GraphQL */ '
        {
            mechanics {
                    id
                      owner {
                        id
                        name
                    }
            }
        }
        ')->assertExactJson(
            [
                "data" => [
                    "mechanics" => [
                       [ "id" => (string) $mechanic->id,
                        "owner" => [
                            "id" => (string) $mechanic_owner->id,
                            "name" => $mechanic_owner->name
                        ]]
                    ]

                ]
            ]
        );
    }
}
