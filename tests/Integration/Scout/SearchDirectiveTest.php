<?php declare(strict_types=1);

namespace Tests\Integration\Scout;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Laravel\Scout\Builder as ScoutBuilder;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Scout\ScoutException;
use Tests\DBTestCase;
use Tests\TestsScoutEngine;
use Tests\Utils\Models\Post;

final class SearchDirectiveTest extends DBTestCase
{
    use TestsScoutEngine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpScoutEngine();
    }

    public function testSearch(): void
    {
        /** @var \Tests\Utils\Models\Post $postA */
        $postA = factory(Post::class)->create([
            'title' => 'great title',
        ]);
        /** @var \Tests\Utils\Models\Post $postB */
        $postB = factory(Post::class)->create([
            'title' => 'Really great title',
        ]);
        factory(Post::class)->create([
            'title' => 'bad title',
        ]);

        $this->engine
            ->shouldReceive('map')
            ->andReturn(
                new EloquentCollection([$postA, $postB]),
            );

        $this->schema = /** @lang GraphQL */ '
        type Post {
            id: ID!
            title: String!
        }

        type Query {
            posts(
                search: String @search
            ): [Post!]! @all
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            posts(search: "great") {
                id
                title
            }
        }
        ')->assertJson([
            'data' => [
                'posts' => [
                    [
                        'id' => $postA->id,
                    ],
                    [
                        'id' => $postB->id,
                    ],
                ],
            ],
        ]);
    }

    public function testSearchWithEq(): void
    {
        $id = 1;

        $this->engine
            ->shouldReceive('map')
            ->withArgs(static fn (ScoutBuilder $builder): bool => $builder->wheres === ['id' => $id])
            ->andReturn(new EloquentCollection())
            ->once();

        $this->schema = /** @lang GraphQL */ '
        type Post {
            id: Int!
        }

        type Query {
            posts(
                id: Int @eq
                search: String @search
            ): [Post!]! @all
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        query ($id: Int) {
            posts(id: $id, search: "great") {
                id
            }
        }
        ', [
            'id' => $id,
        ])->assertJson([
            'data' => [
                'posts' => [],
            ],
        ]);
    }

    public function testSearchWithBuilder(): void
    {
        $id = 1;

        $this->engine
            ->shouldReceive('map')
            ->withArgs(static fn (ScoutBuilder $builder): bool => $builder->wheres === ['from_custom_builder' => $id])
            ->andReturn(new EloquentCollection())
            ->once();

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        type Post {
            id: Int!
        }

        input PostsInput {
            id: Int!
        }

        type Query {
            posts(
                input: PostsInput! @builder(method: "{$this->qualifyTestResolver('customBuilderMethod')}")
                search: String! @search
            ): [Post!]! @all
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ '
        query ($id: Int!) {
            posts(input: { id: $id }, search: "greatness") {
                id
            }
        }
        ', [
            'id' => $id,
        ])->assertJson([
            'data' => [
                'posts' => [],
            ],
        ]);
    }

    /** @param  array{id: int}  $value */
    public static function customBuilderMethod(ScoutBuilder $builder, array $value): ScoutBuilder
    {
        return $builder->where('from_custom_builder', $value['id']);
    }

    public function testSearchWithTrashed(): void
    {
        $this->engine
            ->shouldReceive('map')
            ->withArgs(static fn (ScoutBuilder $builder): bool => $builder->wheres === ['__soft_deleted' => 1])
            ->andReturn(new EloquentCollection())
            ->once();

        $this->schema = /** @lang GraphQL */ '
        type Post {
            id: Int!
        }

        type Query {
            posts(
                id: Int @eq
                search: String @search
            ): [Post!]! @all @softDeletes
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            posts(search: "foo", trashed: ONLY) {
                id
            }
        }
        ')->assertJson([
            'data' => [
                'posts' => [],
            ],
        ]);
    }

    public function testSearchWithinCustomIndex(): void
    {
        /** @var \Tests\Utils\Models\Post $postA */
        $postA = factory(Post::class)->create([
            'title' => 'great title',
        ]);
        /** @var \Tests\Utils\Models\Post $postB */
        $postB = factory(Post::class)->create([
            'title' => 'Really great title',
        ]);
        factory(Post::class)->create([
            'title' => 'bad title',
        ]);

        $myIndex = 'my.index';

        $this->engine
            ->shouldReceive('map')
            ->withArgs(static fn (ScoutBuilder $builder): bool => $builder->index === $myIndex)
            ->andReturn(
                new EloquentCollection([$postA, $postB]),
            )
            ->once();

        $this->schema = /** @lang GraphQL */ "
        type Post {
            id: ID!
        }

        type Query {
            posts(
                search: String @search(within: \"{$myIndex}\")
            ): [Post!]! @all
        }
        ";

        $this->graphQL(/** @lang GraphQL */ '
        {
            posts(search: "great") {
                id
            }
        }
        ')->assertJson([
            'data' => [
                'posts' => [
                    [
                        'id' => $postA->id,
                    ],
                    [
                        'id' => $postB->id,
                    ],
                ],
            ],
        ]);
    }

    public function testWithinMustBeString(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Post {
            id: ID!
        }

        type Query {
            posts(
                search: String @search(within: 123)
            ): [Post!]! @all
        }
        ';

        $this->expectException(DefinitionException::class);

        $this->graphQL(/** @lang GraphQL */ '
        {
            posts(search: "great") {
                id
            }
        }
        ');
    }

    public function testMultipleSearchesAreNotAllowed(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Post {
            id: ID!
        }

        type Query {
            posts(
                first: String @search
                second: String @search
            ): [Post!]! @all
        }
        ';

        $this->expectException(ScoutException::class);

        $this->graphQL(/** @lang GraphQL */ '
        {
            posts(first: "great", second: "nope") {
                id
            }
        }
        ');
    }

    public function testIncompatibleArgBuildersAreNotAllowed(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Post {
            id: ID!
        }

        type Query {
            posts(
                search: String @search
                nope: String @neq
            ): [Post!]! @all
        }
        ';

        $this->expectException(ScoutException::class);

        $this->graphQL(/** @lang GraphQL */ '
        {
            posts(search: "great", nope: "nope") {
                id
            }
        }
        ');
    }

    public function testModelMustBeSearchable(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Task {
            id: ID!
        }

        type Query {
            tasks(
                search: String @search
            ): [Task!]! @all
        }
        ';

        $this->expectException(ScoutException::class);

        $this->graphQL(/** @lang GraphQL */ '
        {
            tasks(search: "great") {
                id
            }
        }
        ');
    }

    public function testHandlesScoutBuilderPaginationArguments(): void
    {
        /** @var \Tests\Utils\Models\Post $postA */
        $postA = factory(Post::class)->create([
            'title' => 'great title',
        ]);
        /** @var \Tests\Utils\Models\Post $postB */
        $postB = factory(Post::class)->create([
            'title' => 'Really great title',
        ]);
        factory(Post::class)->create([
            'title' => 'bad title',
        ]);

        $this->engine->shouldReceive('map')
            ->andReturn(
                new EloquentCollection([$postA, $postB]),
            )
            ->once();

        $this->engine->shouldReceive('paginate')
            ->with(
                \Mockery::any(),
                \Mockery::any(),
                \Mockery::not('page'),
            )
            ->andReturn(new EloquentCollection([$postA, $postB]))
            ->once();

        $this->schema = /** @lang GraphQL */ '
        type Post {
            id: ID!
        }

        type Query {
            posts(
                search: String @search
            ): [Post!]! @paginate
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            posts(first: 10, search: "great") {
                data {
                    id
                }
            }
        }
        ')->assertJson([
            'data' => [
                'posts' => [
                    'data' => [
                        [
                            'id' => "{$postA->id}",
                        ],
                        [
                            'id' => "{$postB->id}",
                        ],
                    ],
                ],
            ],
        ]);
    }
}
