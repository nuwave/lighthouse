<?php

namespace Tests\Integration\Schema\Directives;

use GraphQL\Error\Error;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Pagination\PaginationArgs;
use Tests\DBTestCase;
use Tests\Utils\Models\Image;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class MorphManyDirectiveTest extends DBTestCase
{
    use WithFaker;

    /**
     * Auth user.
     *
     * @var \Tests\Utils\Models\User
     */
    protected $user;

    /**
     * @var \Tests\Utils\Models\Task
     */
    protected $task;

    /**
     * @var \Illuminate\Support\Collection<\Tests\Utils\Models\Image>
     */
    protected $taskImages;

    /**
     * @var \Tests\Utils\Models\Post
     */
    protected $post;

    /**
     * @var \Illuminate\Support\Collection<\Tests\Utils\Models\Image>
     */
    protected $postImages;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = factory(User::class)->create();

        $this->task = factory(Task::class)->create([
            'user_id' => $this->user->id,
        ]);
        $this->taskImages = Collection
            ::times(10)
            ->map(function () {
                return $this->task
                    ->images()
                    ->save(
                        factory(Image::class)->create()
                    );
            });

        $this->post = factory(Post::class)->create([
            'user_id' => $this->user->id,
        ]);
        $this->postImages = Collection
            ::times(
                $this->faker()->numberBetween(1, 10)
            )
            ->map(function () {
                return $this->post
                    ->images()
                    ->save(
                        factory(Image::class)->create()
                    );
            });
    }

    public function testCanQueryMorphManyRelationship(): void
    {
        $this->schema = '
        type Post {
            id: ID!
            title: String!
            images: [Image!] @morphMany
        }

        type Task {
            id: ID!
            name: String!
            images: [Image!] @morphMany
        }

        type Image {
            id: ID!
            from: String
            to: String
        }

        type Query {
            post (
                id: ID! @eq
            ): Post @find

            task (
                id: ID! @eq
            ): Task @find
        }
        ';

        $this->graphQL("
        {
            post(id: {$this->post->id}) {
                id
                title
                images {
                    id
                    from
                    to
                }
            }

            task (id: {$this->task->id}) {
                id
                name
                images {
                    id
                    from
                    to
                }
            }
        }
        ")->assertJson([
            'data' => [
                'post' => [
                    'id' => $this->post->id,
                    'title' => $this->post->title,
                    'images' => $this->postImages
                        ->map(function (Image $image) {
                            return [
                                'id' => $image->id,
                                'from' => $image->from,
                                'to' => $image->to,
                            ];
                        })
                        ->toArray(),
                ],
                'task' => [
                    'id' => $this->task->id,
                    'name' => $this->task->name,
                    'images' => $this->taskImages
                        ->map(function (Image $image) {
                            return [
                                'id' => $image->id,
                                'from' => $image->from,
                                'to' => $image->to,
                            ];
                        })
                        ->toArray(),
                ],
            ],
        ])->assertJsonCount($this->postImages->count(), 'data.post.images')
            ->assertJsonCount($this->taskImages->count(), 'data.task.images');
    }

    public function testCanQueryMorphManyPaginator(): void
    {
        $this->schema = '
        type Post {
            id: ID!
            title: String!
            images: [Image!] @morphMany(type: "paginator")
        }

        type Image {
            id: ID!
            from: String
            to: String
        }

        type Query {
            post (
                id: ID! @eq
            ): Post @find
        }
        ';

        $this->graphQL("
        {
            post(id: {$this->post->id}) {
                id
                title
                images(first: 10) {
                    data {
                        id
                        from
                        to
                    }
                }
            }
        }
        ")->assertJson([
            'data' => [
                'post' => [
                    'id' => $this->post->id,
                    'title' => $this->post->title,
                    'images' => [
                        'data' => $this->postImages
                            ->map(function (Image $image) {
                                return [
                                    'id' => $image->id,
                                    'from' => $image->from,
                                    'to' => $image->to,
                                ];
                            })
                            ->toArray(),
                    ],
                ],
            ],
        ])->assertJsonCount($this->postImages->count(), 'data.post.images.data');
    }

    public function testPaginatorTypeIsLimitedByMaxCountFromDirective(): void
    {
        config(['lighthouse.paginate_max_count' => 1]);

        $this->schema = '
        type Post {
            id: ID!
            title: String!
            images: [Image!] @morphMany(type: "paginator", maxCount: 3)
        }

        type Image {
            id: ID!
            from: String
            to: String
        }

        type Query {
            post (
                id: ID! @eq
            ): Post @find
        }
        ';

        $result = $this->graphQL("
        {
            post(id: {$this->post->id}) {
                id
                title
                images(first: 10) {
                    data {
                        id
                    }
                }
            }
        }
        ");

        $this->assertSame(
            PaginationArgs::requestedTooManyItems(3, 10),
            $result->jsonGet('errors.0.message')
        );
    }

    public function testPaginatorTypeIsLimitedToMaxCountFromConfig(): void
    {
        config(['lighthouse.paginate_max_count' => 2]);

        $this->schema = '
        type Post {
            id: ID!
            title: String!
            images: [Image!] @morphMany(type: "paginator")
        }

        type Image {
            id: ID!
            from: String
            to: String
        }

        type Query {
            post (
                id: ID! @eq
            ): Post @find
        }
        ';

        $result = $this->graphQL("
        {
            post(id: {$this->post->id}) {
                id
                title
                images(first: 10) {
                    data {
                        id
                    }
                }
            }
        }
        ");

        $this->assertSame(
            PaginationArgs::requestedTooManyItems(2, 10),
            $result->jsonGet('errors.0.message')
        );
    }

    public function testHandlesPaginationWithCountZero(): void
    {
        $this->schema = '
        type Post {
            id: ID!
            title: String!
            images: [Image!] @morphMany(type: "paginator")
        }

        type Image {
            id: ID!
            from: String
            to: String
        }

        type Query {
            post (
                id: ID! @eq
            ): Post @find
        }
        ';

        $this->graphQL("
        {
            post(id: {$this->post->id}) {
                id
                title
                images(first: 0) {
                    data {
                        id
                        from
                        to
                    }
                }
            }
        }
        ")->assertJson([
            'data' => [
                'post' => [
                    'id' => $this->post->id,
                    'title' => $this->post->title,
                    'images' => null,
                ],
            ],
        ])->assertErrorCategory(Error::CATEGORY_GRAPHQL);
    }

    public function testCanQueryMorphManyPaginatorWithADefaultCount(): void
    {
        $this->schema = '
        type Task {
            id: ID!
            name: String!
            images: [Image!] @morphMany(type: "paginator", defaultCount: 3)
        }

        type Image {
            id: ID!
            from: String
            to: String
        }

        type Query {
            task (
                id: ID! @eq
            ): Task @find
        }
        ';

        $this->graphQL("
        {
            task(id: {$this->task->id}) {
                id
                name
                images {
                    paginatorInfo {
                        count
                        hasMorePages
                        total
                    }
                    data {
                        id
                    }
                }
            }
        }
        ")->assertJson([
            'data' => [
                'task' => [
                    'id' => $this->task->id,
                    'name' => $this->task->name,
                    'images' => [
                        'paginatorInfo' => [
                            'count' => 3,
                            'hasMorePages' => true,
                            'total' => 10,
                        ],
                    ],
                ],
            ],
        ])->assertJsonCount(3, 'data.task.images.data');
    }

    public function testCanQueryMorphManyRelayConnection(): void
    {
        $this->schema = '
        type Task {
            id: ID!
            name: String!
            images: [Image!] @morphMany(type: "relay")
        }

        type Image {
            id: ID!
            from: String
            to: String
        }

        type Query {
            task (
                id: ID! @eq
            ): Task @find
        }
        ';

        $this->graphQL("
        {
            task(id: {$this->task->id}) {
                id
                name
                images(first: 3) {
                    pageInfo {
                        hasNextPage
                    }
                    edges {
                        node {
                            id
                        }
                    }
                }
            }
        }
        ")->assertJson([
            'data' => [
                'task' => [
                    'id' => $this->task->id,
                    'name' => $this->task->name,
                    'images' => [
                        'pageInfo' => [
                            'hasNextPage' => true,
                        ],
                    ],
                ],
            ],
        ])->assertJsonCount(3, 'data.task.images.edges');
    }

    public function testRelayTypeIsLimitedByMaxCountFromDirective(): void
    {
        config(['lighthouse.paginate_max_count' => 1]);

        $this->schema = '
        type Task {
            id: ID!
            name: String!
            images: [Image!] @morphMany(type: "relay", maxCount: 3)
        }

        type Image {
            id: ID!
            from: String
            to: String
        }

        type Query {
            task (
                id: ID! @eq
            ): Task @find
        }
        ';

        $result = $this->graphQL("
        {
            task(id: {$this->task->id}) {
                id
                name
                images(first: 10) {
                    edges {
                        node {
                            id
                        }
                    }
                }
            }
        }
        ");

        $this->assertSame(
            PaginationArgs::requestedTooManyItems(3, 10),
            $result->jsonGet('errors.0.message')
        );
    }

    public function testRelayTypeIsLimitedToMaxCountFromConfig(): void
    {
        config(['lighthouse.paginate_max_count' => 2]);

        $this->schema = '
        type Task {
            id: ID!
            name: String!
            images: [Image!] @morphMany(type: "relay")
        }

        type Image {
            id: ID!
            from: String
            to: String
        }

        type Query {
            task (
                id: ID! @eq
            ): Task @find
        }
        ';

        $result = $this->graphQL("
        {
            task(id: {$this->task->id}) {
                id
                name
                images(first: 10) {
                    edges {
                        node {
                            id
                        }
                    }
                }
            }
        }
        ");

        $this->assertSame(
            PaginationArgs::requestedTooManyItems(2, 10),
            $result->jsonGet('errors.0.message')
        );
    }

    public function testCanQueryMorphManyRelayConnectionWithADefaultCount(): void
    {
        $this->schema = '
        type Task {
            id: ID!
            name: String!
            images: [Image!] @morphMany(type: "relay", defaultCount: 3)
        }

        type Image {
            id: ID!
            from: String
            to: String
        }

        type Query {
            task (
                id: ID! @eq
            ): Task @find
        }
        ';

        $this->graphQL("
        {
            task(id: {$this->task->id}) {
                id
                name
                images {
                    pageInfo {
                        hasNextPage
                    }
                    edges {
                        node {
                            id
                        }
                    }
                }
            }
        }
        ")->assertJson([
            'data' => [
                'task' => [
                    'id' => $this->task->id,
                    'name' => $this->task->name,
                    'images' => [
                        'pageInfo' => [
                            'hasNextPage' => true,
                        ],
                    ],
                ],
            ],
        ])->assertJsonCount(3, 'data.task.images.edges');
    }
}
