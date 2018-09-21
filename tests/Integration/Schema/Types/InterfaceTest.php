<?php

namespace Tests\Integration\Schema\Types;

use Tests\DBTestCase;
use Tests\Utils\Models\Team;
use Tests\Utils\Models\User;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InterfaceTest extends DBTestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function itCanResolveInterfaceTypes()
    {
        // This creates one team with it
        factory(User::class)->create();

        $schema = '
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
        $query = '
        {
            namedThings {
                name
                ... on User {
                    id
                }
            }
        }
        ';
        $result = $this->execute($schema, $query);

        $this->assertCount(2, array_get($result, 'data.namedThings'));
        $this->assertArrayHasKey('name', array_get($result, 'data.namedThings.0'));
        $this->assertArrayHasKey('id', array_get($result, 'data.namedThings.0'));
        $this->assertArrayHasKey('name', array_get($result, 'data.namedThings.1'));
        $this->assertArrayNotHasKey('id', array_get($result, 'data.namedThings.1'));
    }

    /**
     * @test
     */
    public function itCanUseCustomTypeResolver()
    {
        $schema = '
        interface Nameable @interface(resolver: "'.addslashes(self::class).'@resolveType"){
            name: String!
        }

        type Guy implements Nameable {
            name: String!
        }

        type Query {
            namedThings: Nameable @field(resolver: "'.addslashes(self::class).'@fetchGuy")
        }
        ';
        $query = '
        {
            namedThings {
                name
                ... on Guy {
                    id
                }
            }
        }
        ';
        $result = $this->execute($schema, $query);

        $this->assertSame($this->fetchGuy(), $result['data']['namedThings']);
    }

    /**
     * @test
     */
    public function itCanListPossibleTypes()
    {
        // This creates one team with it
        factory(User::class)->create();

        $schema = '
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
        $query = '{
            __schema {
                types {
                    kind
                    name
                    possibleTypes {
                        name
                    }
                }
            }
        }';

        $result = $this->execute($schema, $query);
        $types = array_get($result, 'data.__schema.types');
        $interface = collect($types)->firstWhere('name', 'Nameable');

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
        return resolve(TypeRegistry::class)->get('Guy');
    }

    public function fetchGuy(): array
    {
        return ['name' => 'bar'];
    }
}
