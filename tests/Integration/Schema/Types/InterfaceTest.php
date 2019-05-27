<?php

namespace Tests\Integration\Schema\Types;

use Tests\DBTestCase;
use Tests\Utils\Models\Team;
use Tests\Utils\Models\User;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class InterfaceTest extends DBTestCase
{
    /**
     * @test
     */
    public function itCanResolveInterfaceTypes(): void
    {
        // This creates one team with it
        factory(User::class)->create();

        $this->schema = '
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
            namedThings: [Nameable!]! @field(resolver: "'.$this->qualifyTestResolver('fetchResults').'")
        }
        ';

        $result = $this->graphQL('
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

        $this->assertArrayNotHasKey('id', $result->jsonGet('data.namedThings.1'));
    }

    /**
     * @test
     */
    public function itCanUseCustomTypeResolver(): void
    {
        $this->schema = '
        interface Nameable @interface(resolveType: "'.$this->qualifyTestResolver('resolveType').'"){
            name: String!
        }

        type Guy implements Nameable {
            id: ID!
            name: String!
        }

        type Query {
            namedThings: Nameable @field(resolver: "'.$this->qualifyTestResolver('fetchGuy').'")
        }
        ';

        $this->graphQL('
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

    /**
     * @test
     */
    public function itCanListPossibleTypes(): void
    {
        // This creates one team with it
        factory(User::class)->create();

        $this->schema = '
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
            namedThings: [Nameable!]! @field(resolver: "'.$this->qualifyTestResolver('fetchResults').'")
        }
        ';

        $result = $this->graphQL('
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

        $interface = (new Collection($result->jsonGet('data.__schema.types')))
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

    public function fetchGuy(): array
    {
        return [
            'name' => 'bar',
            'id' => '1',
        ];
    }
}
