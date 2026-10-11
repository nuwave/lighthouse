<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Types;

use GraphQL\Error\InvariantViolation;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

final class UnionTest extends DBTestCase
{
    /** @dataProvider withAndWithoutCustomTypeResolver */
    #[DataProvider('withAndWithoutCustomTypeResolver')]
    public function testResolveUnionTypes(string $schema, string $query): void
    {
        // This creates a user with it.
        $task = factory(Task::class)->create();
        $post = new Post();
        $post->title = 'foo';
        $post->body = 'bar';
        $post->user()->associate(factory(User::class)->create());
        $post->task()->associate($task);
        $post->save();

        $this->schema = $schema;

        $this->graphQL($query)->assertJsonStructure([
            'data' => [
                'stuff' => [
                    [
                        'name',
                    ],
                    [
                        'title',
                    ],
                ],
            ],
        ]);
    }

    public function testConsidersRenamedModels(): void
    {
        // This creates a user with it.
        $task = factory(Task::class)->create();
        $post = new Post();
        $post->title = 'foo';
        $post->body = 'bar';
        $post->user()->associate(factory(User::class)->create());
        $post->task()->associate($task);
        $post->save();

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        union Stuff = Foo | Post

        type Foo @model(class: "User") {
            name: String!
        }

        type Post {
            title: String!
        }

        type Query {
            stuff: [Stuff!]! @field(resolver: "{$this->qualifyTestResolver('fetchResults')}")
        }
GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
                {
                    stuff {
                        ... on Foo {
                            name
                        }
                        ... on Post {
                            title
                        }
                    }
                }
        GRAPHQL)->assertJsonStructure([
            'data' => [
                'stuff' => [
                    [
                        'name',
                    ],
                    [
                        'title',
                    ],
                ],
            ],
        ]);
    }

    public function testRejectsUnionWithString(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ <<<GRAPHQL
        union Stuff = String
GRAPHQL . "\n",
        );

        $this->expectExceptionObject(new InvariantViolation(
            'Union type Stuff can only include Object types, it cannot include String.',
        ));
        $schema->assertValid();
    }

    public function testThrowsOnAmbiguousSchemaMapping(): void
    {
        // This creates a user with it.
        $task = factory(Task::class)->create();
        $post = new Post();
        $post->title = 'foo';
        $post->body = 'bar';
        $post->user()->associate(factory(User::class)->create());
        $post->task()->associate($task);
        $post->save();

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        union Nameable = Foo | Post

        type Foo @model(class: "User") {
            name: String!
        }

        type Post @model(class: "User") {
            title: String!
        }

        type Query {
            stuff: [Nameable!]! @field(resolver: "{$this->qualifyTestResolver('fetchResults')}")
        }
GRAPHQL;

        $this->expectExceptionObject(
            TypeRegistry::unresolvableAbstractTypeMapping(User::class, ['Foo', 'Post']),
        );
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
                {
                    stuff {
                        ... on Foo {
                            name
                        }
                        ... on Post {
                            title
                        }
                    }
                }
        GRAPHQL);
    }

    public function testThrowsOnNonOverlappingSchemaMapping(): void
    {
        // This creates a user with it.
        $task = factory(Task::class)->create();
        $post = new Post();
        $post->title = 'foo';
        $post->body = 'bar';
        $post->user()->associate(factory(User::class)->create());
        $post->task()->associate($task);
        $post->save();

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        union Stuff = Post

        type Post {
            title: String!
        }

        type NotPartOfUnion @model(class: "User") {
            id: String!
        }

        type Query {
            stuff: [Stuff!]! @field(resolver: "{$this->qualifyTestResolver('fetchResults')}")
        }
GRAPHQL;

        $this->expectExceptionObject(
            TypeRegistry::unresolvableAbstractTypeMapping(User::class, []),
        );
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
                {
                    stuff {
                        ... on Post {
                            title
                        }
                    }
                }
        GRAPHQL);
    }

    /** @return \Illuminate\Support\Collection<int, \Tests\Utils\Models\User|\Tests\Utils\Models\Post> */
    public static function fetchResults(): Collection
    {
        /** @var \Illuminate\Support\Collection<int, \Tests\Utils\Models\User|\Tests\Utils\Models\Post> $results */
        $results = new Collection();

        return $results
            ->concat(User::all())
            ->concat(Post::all());
    }

    /** @return iterable<array{string, string}> */
    public static function withAndWithoutCustomTypeResolver(): iterable
    {
        yield 'default type resolver' => self::schemaAndQuery(false);
        yield 'custom resolver, since the types User and Post do not match' => self::schemaAndQuery(true);
    }

    /** @return array{string, string} */
    public static function schemaAndQuery(bool $withCustomTypeResolver): array
    {
        $prefix = $withCustomTypeResolver
            ? 'Custom'
            : '';

        $customResolver = $withCustomTypeResolver
            ? /** @lang GraphQL */ <<<'GRAPHQL'
            @union(resolveType: "Tests\\Utils\\Unions\\CustomStuff@resolveType")
            GRAPHQL
            : '';

        $fetchResultsResolver = self::qualifyTestResolver('fetchResults');

        return [
/** @lang GraphQL */ <<<GRAPHQL
            union Stuff {$customResolver} = {$prefix}User | {$prefix}Post

            type {$prefix}User {
                name: String!
            }

            type {$prefix}Post {
                title: String!
            }

            type Query {
                stuff: [Stuff!]! @field(resolver: "{$fetchResultsResolver}")
            }
GRAPHQL,
/** @lang GraphQL */ <<<GRAPHQL
            {
                stuff {
                    ... on {$prefix}User {
                        name
                    }
                    ... on {$prefix}Post {
                        title
                    }
                }
            }
GRAPHQL,
        ];
    }
}
