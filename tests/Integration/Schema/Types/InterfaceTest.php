<?php

namespace Tests\Integration\Schema\Types;

use GraphQL\Type\Definition\Type;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Tests\DBTestCase;
use Tests\Utils\Models\Team;
use Tests\Utils\Models\User;

final class InterfaceTest extends DBTestCase
{
    public function testResolveInterfaceTypes(): void
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

    public function testConsidersRenamedModels(): void
    {
        // This creates one team with it
        factory(User::class)->create();

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        interface Nameable {
            name: String!
        }

        type Foo implements Nameable @model(class: "User") {
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
                ... on Foo {
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

    public function testDoesNotErrorOnSecondRenamedModel(): void
    {
        // This creates one team with it
        factory(User::class)->create();

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        interface Nameable {
            name: String!
        }

        type Foo implements Nameable @model(class: "Team") {
            name: String!
        }

        type Bar implements Nameable @model(class: "User") {
            name: String!
        }

        type Query {
            namedThings: [Nameable!]! @field(resolver: "{$this->qualifyTestResolver('fetchResults')}")
        }
GRAPHQL;

        $this->expectNotToPerformAssertions();
        $this->graphQL(/** @lang GraphQL */ '
        {
            namedThings {
                name
            }
        }
        ');
    }

    public function testThrowsOnAmbiguousSchemaMapping(): void
    {
        // This creates one team with it
        factory(User::class)->create();

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        interface Nameable {
            name: String!
        }

        type Foo implements Nameable @model(class: "User") {
            name: String!
        }

        type Team implements Nameable @model(class: "User") {
            name: String!
        }

        type Query {
            namedThings: [Nameable!]! @field(resolver: "{$this->qualifyTestResolver('fetchResults')}")
        }
GRAPHQL;

        $this->expectExceptionObject(
            TypeRegistry::unresolvableAbstractTypeMapping(User::class, ['Foo', 'Team'])
        );
        $this->graphQL(/** @lang GraphQL */ '
        {
            namedThings {
                name
            }
        }
        ');
    }

    public function testThrowsOnNonOverlappingSchemaMapping(): void
    {
        // This creates one team with it
        factory(User::class)->create();

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        interface Nameable {
            name: String!
        }

        type Team implements Nameable {
            name: String!
        }

        type NotPartOfInterface @model(class: "User") {
            id: String!
        }

        type Query {
            namedThings: [Nameable!]! @field(resolver: "{$this->qualifyTestResolver('fetchResults')}")
        }
GRAPHQL;

        $this->expectExceptionObject(
            TypeRegistry::unresolvableAbstractTypeMapping(User::class, [])
        );
        $this->graphQL(/** @lang GraphQL */ '
        {
            namedThings {
                name
            }
        }
        ');
    }

    public function testUseCustomTypeResolver(): void
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

    public function testListPossibleTypes(): void
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

    /**
     * @return \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\User|\Tests\Utils\Models\Team>
     */
    public function fetchResults(): EloquentCollection
    {
        /** @var \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\User|\Tests\Utils\Models\Team> $results */
        $results = new EloquentCollection();

        return $results
            ->concat(User::all())
            ->concat(Team::all());
    }

    public function resolveType(): Type
    {
        $typeRegistry = $this->app->make(TypeRegistry::class);
        assert($typeRegistry instanceof TypeRegistry);

        return $typeRegistry->get('Guy');
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
