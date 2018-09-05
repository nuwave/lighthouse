<?php

namespace Tests\Integration\Schema\Directives\Fields;

use Tests\DBTestCase;
use Tests\Utils\Models\Team;
use Tests\Utils\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InterfaceDirectiveTest extends DBTestCase
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
        interface Nameable @interface(resolver: "' . addslashes(self::class) . '@resolveNameableInterface") {
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
            namedThings: [Nameable!]! @field(resolver: "' . addslashes(self::class) . '@fetchResults")
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

    public function resolveNameableInterface($value): \GraphQL\Type\Definition\ObjectType
    {
        if ($value instanceof User) {
            return graphql()->types()->get('User');
        } elseif($value instanceof Team){
            return graphql()->types()->get('Team');
        }
    }

    public function fetchResults(): Collection
    {
        $users = User::all();
        $teams = Team::all();

        return $users->concat($teams);
    }
}
