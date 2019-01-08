<?php

namespace Tests\Integration\Schema\Types;

use Tests\DBTestCase;
use Tests\Utils\Models\Team;
use Tests\Utils\Models\User;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Illuminate\Database\Eloquent\Collection;

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
            namedThings: [Nameable!]! @field(resolver: "'.addslashes(self::class).'@fetchResults")
        }
        ';

        $result = $this->query('
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
        interface Nameable @interface(resolveType: "'.addslashes(self::class).'@resolveType"){
            name: String!
        }

        type Guy implements Nameable {
            id: ID!
            name: String!
        }

        type Query {
            namedThings: Nameable @field(resolver: "'.addslashes(self::class).'@fetchGuy")
        }
        ';

        $this->query('
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
            namedThings: [Nameable!]! @field(resolver: "'.addslashes(self::class).'@fetchResults")
        }
        ';

        $result = $this->query('{
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

        $interface = collect($result->jsonGet('data.__schema.types'))
            ->firstWhere('name', 'Nameable');

        $this->assertCount(2, $interface['possibleTypes']);
    }

    public function fetchResults(): Collection
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
