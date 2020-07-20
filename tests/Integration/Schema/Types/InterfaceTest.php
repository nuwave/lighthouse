<?php

namespace Tests\Integration\Schema\Types;

use GraphQL\Type\Definition\Type;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Tests\DBTestCase;
use Tests\Utils\Models\Team;
use Tests\Utils\Models\User;

class InterfaceTest extends DBTestCase
{
    public function testCanResolveInterfaceTypes(): void
    {
        // This creates one team with it
        factory(User::class)->create();

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        interface Nameable {
            name: String!
        }

        type User implements Nameable {
            id: ID!
            name: String!
        }

        type Team implements Nameable {
            name: String!
        }

        type Query {
            namedThings: [Nameable!]! @field(resolver: "{$this->qualifyTestResolver('fetchResults')}")
        }
GRAPHQL;

        $result = $this->graphQL(/** @lang GraphQL */ '
        {
            namedThings {
                name
                ... on User {
                    id
                }
            }
        }
        ')->assertJsonStructure([
            'data' => [
                'namedThings' => [
                    [
                        'name',
                        'id',
                    ],
                    [
                        'name',
                    ],
                ],
            ],
        ]);

        $this->assertArrayNotHasKey('id', $result->json('data.namedThings.1'));
    }

    public function testCanUseCustomTypeResolver(): void
    {
        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        interface Nameable @interface(resolveType: "{$this->qualifyTestResolver('resolveType')}"){
            name: String!
        }

        type Guy implements Nameable {
            id: ID!
            name: String!
        }

        type Query {
            namedThings: Nameable @field(resolver: "{$this->qualifyTestResolver('fetchGuy')}")
        }
GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ '
        {
            namedThings {
                name
                ... on Guy {
                    id
                }
            }
        }
        ')->assertJson([
            'data' => [
                'namedThings' => $this->fetchGuy(),
            ],
        ]);
    }

    public function testCanListPossibleTypes(): void
    {
        // This creates one team with it
        factory(User::class)->create();

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        interface Nameable {
            name: String!
        }

        type User implements Nameable {
            id: ID!
            name: String!
        }

        type Team implements Nameable {
            name: String!
        }

        type Query {
            namedThings: [Nameable!]! @field(resolver: "{$this->qualifyTestResolver('fetchResults')}")
        }
GRAPHQL;

        $result = $this->graphQL(/** @lang GraphQL */ '
        {
            __schema {
                types {
                    kind
                    name
                    possibleTypes {
                        name
                    }
                }
            }
        }
        ');

        $interface = (new Collection($result->json('data.__schema.types')))
            ->firstWhere('name', 'Nameable');

        $this->assertCount(2, $interface['possibleTypes']);
    }

    public function fetchResults(): EloquentCollection
    {
        $users = User::all();
        $teams = Team::all();

        return $users->concat($teams);
    }

    public function resolveType(): Type
    {
        return app(TypeRegistry::class)->get('Guy');
    }

    /**
     * @return array<string, string>
     */
    public function fetchGuy(): array
    {
        return [
            'name' => 'bar',
            'id' => '1',
        ];
    }
}
