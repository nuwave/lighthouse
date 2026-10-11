<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Types;

use GraphQL\Type\Definition\Type;
use Illuminate\Container\Container;
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

        $result = $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            namedThings {
                name
                ... on User {
                    id
                }
            }
        }
        GRAPHQL)->assertJsonStructure([
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

        $result = $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            namedThings {
                name
                ... on Foo {
                    id
                }
            }
        }
        GRAPHQL)->assertJsonStructure([
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
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            namedThings {
                name
            }
        }
        GRAPHQL);
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
            TypeRegistry::unresolvableAbstractTypeMapping(User::class, ['Foo', 'Team']),
        );
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            namedThings {
                name
            }
        }
        GRAPHQL);
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
            TypeRegistry::unresolvableAbstractTypeMapping(User::class, []),
        );
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            namedThings {
                name
            }
        }
        GRAPHQL);
    }

    public function testUseCustomTypeResolver(): void
    {
        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        interface Nameable @interface(resolveType: "{$this->qualifyTestResolver('resolveType')}") {
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

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            namedThings {
                name
                ... on Guy {
                    id
                }
            }
        }
        GRAPHQL)->assertJson([
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

        $result = $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL);

        $interface = (new Collection($result->json('data.__schema.types')))
            ->firstWhere('name', 'Nameable');

        $this->assertCount(2, $interface['possibleTypes']);
    }

    public function testInterfaceManipulation(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        interface HasPosts {
            posts: [Post!]! @paginate
        }

        type Post {
            id: ID!
        }

        type User implements HasPosts {
            id: ID!
            posts: [Post!]! @paginate
        }

        type Team implements HasPosts {
            posts: [Post!]! @paginate
        }

        type Query {
            foo: String
        }
        GRAPHQL;

        $result = $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            __type(name: "HasPosts") {
                name
                kind
                fields {
                    name
                    type {
                        ofType {
                            name
                            kind
                        }
                    }
                }
            }
        }
        GRAPHQL);

        $this->assertSame('HasPosts', $result->json('data.__type.name'));
        $this->assertSame('INTERFACE', $result->json('data.__type.kind'));
        $this->assertSame('PostPaginator', $result->json('data.__type.fields.0.type.ofType.name'));
    }

    /** @return \Illuminate\Support\Collection<int, \Tests\Utils\Models\User|\Tests\Utils\Models\Team> */
    public static function fetchResults(): Collection
    {
        /** @var \Illuminate\Support\Collection<int, \Tests\Utils\Models\User|\Tests\Utils\Models\Team> $results */
        $results = new Collection();

        return $results
            ->concat(User::all())
            ->concat(Team::all());
    }

    public static function resolveType(): Type
    {
        $typeRegistry = Container::getInstance()->make(TypeRegistry::class);

        return $typeRegistry->get('Guy');
    }

    /** @return array<string, string> */
    public static function fetchGuy(): array
    {
        return [
            'name' => 'bar',
            'id' => '1',
        ];
    }
}
