<?php

namespace Tests\Integration\Schema\Directives;

use GraphQL\Error\Error;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Collection;
use Tests\DBTestCase;
use Tests\Utils\Models\Hour;
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
     * User's task.
     *
     * @var \Tests\Utils\Models\Task
     */
    protected $task;

    /**
     * Task's hours.
     *
     * @var Collection
     */
    protected $taskHours;

    /**
     * User's post.
     *
     * @var \Tests\Utils\Models\Post
     */
    protected $post;

    /**
     * Post's hours.
     *
     * @var Collection
     */
    protected $postHours;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = factory(User::class)->create();

        $this->task = factory(Task::class)->create([
            'user_id' => $this->user->id,
        ]);
        $this->taskHours = Collection
            ::times(10)
            ->map(function () {
                return $this->task
                    ->hours()
                    ->save(
                        factory(Hour::class)->create()
                    );
            });

        $this->post = factory(Post::class)->create([
            'user_id' => $this->user->id,
        ]);
        $this->postHours = Collection
            ::times(
                $this->faker()->numberBetween(1, 10)
            )
            ->map(function () {
                return $this->post
                    ->hours()
                    ->save(
                        factory(Hour::class)->create()
                    );
            });
    }

    public function testCanQueryMorphManyRelationship(): void
    {
        $this->schema = '
        type Post {
            id: ID!
            title: String!
            hours: [Hour!] @morphMany
        }
        
        type Task {
            id: ID!
            name: String!
            hours: [Hour!] @morphMany
        }
        
        type Hour {
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
                hours {
                    id
                    from
                    to
                }
            }
            
            task (id: {$this->task->id}) {
                id
                name
                hours {
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
                    'hours' => $this->postHours
                        ->map(function (Hour $hour) {
                            return [
                                'id' => $hour->id,
                                'from' => $hour->from,
                                'to' => $hour->to,
                            ];
                        })
                        ->toArray(),
                ],
                'task' => [
                    'id' => $this->task->id,
                    'name' => $this->task->name,
                    'hours' => $this->taskHours
                        ->map(function (Hour $hour) {
                            return [
                                'id' => $hour->id,
                                'from' => $hour->from,
                                'to' => $hour->to,
                            ];
                        })
                        ->toArray(),
                ],
            ],
        ])->assertJsonCount($this->postHours->count(), 'data.post.hours')
            ->assertJsonCount($this->taskHours->count(), 'data.task.hours');
    }

    public function testCanQueryMorphManyPaginator(): void
    {
        $this->schema = '
        type Post {
            id: ID!
            title: String!
            hours: [Hour!] @morphMany(type: "paginator")
        }
        
        type Hour {
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
                hours(first: 10) {
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
                    'hours' => [
                        'data' => $this->postHours
                            ->map(function (Hour $hour) {
                                return [
                                    'id' => $hour->id,
                                    'from' => $hour->from,
                                    'to' => $hour->to,
                                ];
                            })
                            ->toArray(),
                    ],
                ],
            ],
        ])->assertJsonCount($this->postHours->count(), 'data.post.hours.data');
    }

    public function testPaginatorTypeIsLimitedByMaxCountFromDirective(): void
    {
        config(['lighthouse.paginate_max_count' => 1]);

        $this->schema = '
        type Post {
            id: ID!
            title: String!
            hours: [Hour!] @morphMany(type: "paginator", maxCount: 3)
        }
        
        type Hour {
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
                hours(first: 10) {
                    data {
                        id
                    }
                }
            }
        }
        ");

        $this->assertSame(
            'Maximum number of 3 requested items exceeded. Fetch smaller chunks.',
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
            hours: [Hour!] @morphMany(type: "paginator")
        }
        
        type Hour {
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
                hours(first: 10) {
                    data {
                        id
                    }
                }
            }
        }
        ");

        $this->assertSame(
            'Maximum number of 2 requested items exceeded. Fetch smaller chunks.',
            $result->jsonGet('errors.0.message')
        );
    }

    public function testHandlesPaginationWithCountZero(): void
    {
        $this->schema = '
        type Post {
            id: ID!
            title: String!
            hours: [Hour!] @morphMany(type: "paginator")
        }
        
        type Hour {
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
                hours(first: 0) {
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
                    'hours' => null,
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
            hours: [Hour!] @morphMany(type: "paginator", defaultCount: 3)
        }
        
        type Hour {
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
                hours {
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
                    'hours' => [
                        'paginatorInfo' => [
                            'count' => 3,
                            'hasMorePages' => true,
                            'total' => 10,
                        ],
                    ],
                ],
            ],
        ])->assertJsonCount(3, 'data.task.hours.data');
    }

    public function testCanQueryMorphManyRelayConnection(): void
    {
        $this->schema = '
        type Task {
            id: ID!
            name: String!
            hours: [Hour!] @morphMany(type: "relay")
        }
        
        type Hour {
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
                hours(first: 3) {
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
                    'hours' => [
                        'pageInfo' => [
                            'hasNextPage' => true,
                        ],
                    ],
                ],
            ],
        ])->assertJsonCount(3, 'data.task.hours.edges');
    }

    public function testRelayTypeIsLimitedByMaxCountFromDirective(): void
    {
        config(['lighthouse.paginate_max_count' => 1]);

        $this->schema = '
        type Task {
            id: ID!
            name: String!
            hours: [Hour!] @morphMany(type: "relay", maxCount: 3)
        }
        
        type Hour {
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
                hours(first: 10) {
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
            'Maximum number of 3 requested items exceeded. Fetch smaller chunks.',
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
            hours: [Hour!] @morphMany(type: "relay")
        }
        
        type Hour {
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
                hours(first: 10) {
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
            'Maximum number of 2 requested items exceeded. Fetch smaller chunks.',
            $result->jsonGet('errors.0.message')
        );
    }

    public function testCanQueryMorphManyRelayConnectionWithADefaultCount(): void
    {
        $this->schema = '
        type Task {
            id: ID!
            name: String!
            hours: [Hour!] @morphMany(type: "relay", defaultCount: 3)
        }
        
        type Hour {
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
                hours {
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
                    'hours' => [
                        'pageInfo' => [
                            'hasNextPage' => true,
                        ],
                    ],
                ],
            ],
        ])->assertJsonCount(3, 'data.task.hours.edges');
    }
}
