<?php declare(strict_types=1);

namespace Tests\Integration\CacheControl;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\DBTestCase;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\Team;
use Tests\Utils\Models\User;

final class CacheControlDirectiveTest extends DBTestCase
{
    public function testDefaultAppResponseHeader(): void
    {
        $this->mockResolver([
            'id' => 1,
            'name' => 'foobar',
        ]);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
            name: String
        }

        type Query {
            user: User @mock
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            user {
                name
            }
        }
        GRAPHQL)->assertHeader('Cache-Control', 'no-cache, private');
    }

    public function testInheritance(): void
    {
        $this->mockResolver([
            'id' => 1,
        ]);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
        }

        type Query {
            me: User @mock @cacheControl(maxAge: 5, scope: PRIVATE)
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            me {
                id
            }
        }
        GRAPHQL)->assertHeader('Cache-Control', 'max-age=5, private');
    }

    /** @dataProvider rootScalarDataProvider */
    #[DataProvider('rootScalarDataProvider')]
    public function testRootScalar(string $query, string $expectedHeaderString): void
    {
        $this->mockResolver(1);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            default: ID @mock
            withDirective: ID @mock @cacheControl(maxAge: 5)
        }
        GRAPHQL;

        $this->graphQL($query)
            ->assertHeader('Cache-Control', $expectedHeaderString);
    }

    /** @return iterable<array{string, string}> */
    public static function rootScalarDataProvider(): iterable
    {
        yield [/** @lang GraphQL */ <<<'GRAPHQL'
            {
                default
            }
        GRAPHQL,
            'no-cache, private',
        ];
        yield [/** @lang GraphQL */ <<<'GRAPHQL'
            {
                withDirective
            }
        GRAPHQL,
            'max-age=5, public',
        ];
        yield [/** @lang GraphQL */ <<<'GRAPHQL'
            {
                default
                withDirective
            }
        GRAPHQL,
            'no-cache, private',
        ];
    }

    public function testInheritanceWithNonScalar(): void
    {
        $this->mockResolver([
            'id' => 1,
            'self' => null,
        ]);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: ID!
            child: User
        }

        type Query {
            me: User @mock @cacheControl(maxAge: 5, scope: PRIVATE)
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            me {
                child {
                    id
                }
            }
        }
        GRAPHQL)->assertHeader('Cache-Control', 'no-cache, private');
    }

    /** @dataProvider argumentsDataProvider */
    #[DataProvider('argumentsDataProvider')]
    public function testDirectiveArguments(string $directive, string $expectedHeaderString): void
    {
        $this->mockResolver([
            'id' => 1,
            'name' => 'foobar',
        ]);

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        type User {
            id: ID!
            name: String {$directive}
        }

        type Query {
            user: User @mock @cacheControl(maxAge:50)
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            user {
                name
            }
        }
        GRAPHQL)->assertHeader('Cache-Control', $expectedHeaderString);
    }

    /** @return iterable<array{string, string}> */
    public static function argumentsDataProvider(): iterable
    {
        yield 'noArguments' => ['@cacheControl', 'no-cache, public'];
        yield 'onlyMaxAge' => ['@cacheControl(maxAge: 10)', 'max-age=10, public'];
        yield 'onlyScope' => ['@cacheControl(scope: PRIVATE)', 'no-cache, private'];
        yield 'inheritMaxAge' => ['@cacheControl(inheritMaxAge: true)', 'max-age=50, public'];
        yield 'inheritMaxAgeDenyMaxAge' => ['@cacheControl(maxAge: 0, inheritMaxAge: true)', 'max-age=50, public'];
        yield 'maxAgePrivate' => ['@cacheControl(maxAge:10, scope: PRIVATE)', 'max-age=10, private'];
    }

    /** @dataProvider nestedQueryDataProvider */
    #[DataProvider('nestedQueryDataProvider')]
    public function testUseDirectiveNested(string $query, string $expectedHeaderString): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            tasks: [Task!]! @hasMany @cacheControl(maxAge: 50)
            posts: [Post!]! @hasMany
        }

        type Post {
            id: Int @cacheControl(maxAge: 10)
            foo: String @cacheControl(scope: PRIVATE, inheritMaxAge: true)
        }

        type Team {
            users: [User!]! @hasMany @cacheControl(maxAge: 25)
        }

        type Task {
            id: Int @cacheControl(maxAge: 10)
            foo: String @cacheControl(inheritMaxAge: true)
            bar: String
        }

        type Query {
            user: User @first @cacheControl(maxAge: 5, scope: PRIVATE)
            team: Team @first @cacheControl
            teamWithCache: Team @first @cacheControl(maxAge: 20)
        }
        GRAPHQL;

        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $tasks = factory(Task::class, 3)->make();
        $user->tasks()->saveMany($tasks);

        $posts = factory(Post::class, 3)->make();
        $user->posts()->saveMany($posts);

        $team = factory(Team::class)->create();
        $this->assertInstanceOf(Team::class, $team);

        $users = factory(User::class, 3)->make();
        $team->users()->saveMany($users);

        $this->graphQL($query)
            ->assertHeader('Cache-Control', $expectedHeaderString);
    }

    /** @return iterable<array{string, string}> */
    public static function nestedQueryDataProvider(): iterable
    {
        yield [/** @lang GraphQL */ <<<'GRAPHQL'
            {
                user {
                    tasks {
                        id
                        foo
                    }
                }
            }
        GRAPHQL,
            'max-age=5, private',
        ];
        yield [/** @lang GraphQL */ <<<'GRAPHQL'
            {
                user {
                    tasks {
                        id
                    }
                }
            }
        GRAPHQL,
            'max-age=5, private',
        ];
        yield [/** @lang GraphQL */ <<<'GRAPHQL'
            {
                team {
                    users {
                        tasks  {
                            id
                        }
                    }
                }
            }
        GRAPHQL,
            'no-cache, public',
        ];
        yield [/** @lang GraphQL */ <<<'GRAPHQL'
            {
                team {
                    users {
                        tasks  {
                            foo
                        }
                    }
                }
            }
        GRAPHQL,
            'no-cache, public',
        ];
        yield [/** @lang GraphQL */ <<<'GRAPHQL'
            {
                teamWithCache {
                    users {
                        tasks  {
                            bar
                        }
                    }
                }
            }
        GRAPHQL,
            'max-age=20, public',
        ];
        yield [/** @lang GraphQL */ <<<'GRAPHQL'
            {
                teamWithCache {
                    users {
                        posts  {
                            id
                        }
                    }
                }
            }
        GRAPHQL,
            'no-cache, public',
        ];
        yield [/** @lang GraphQL */ <<<'GRAPHQL'
            {
                teamWithCache {
                    users {
                        posts  {
                            id
                            foo
                        }
                    }
                }
            }
        GRAPHQL,
            'no-cache, private',
        ];
    }

    public function testUsePaginate(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            tasks: [Task!]! @hasMany(type: PAGINATOR) @cacheControl(maxAge: 50)
        }

        type Task {
            id: Int @cacheControl(maxAge: 10)
            foo: String @cacheControl(inheritMaxAge: true)
            bar: String
        }

        type Query {
            users: [User] @paginate @cacheControl(maxAge: 5, scope: PRIVATE)
        }
        GRAPHQL;

        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $tasks = factory(Task::class, 3)->make();
        $user->tasks()->saveMany($tasks);

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                users(first: 10) {
                    paginatorInfo {
                        count
                    }
                   data {
                        tasks(first: 10) {
                            data {
                                id
                                foo
                            }
                        }
                   }
                }
            }
        GRAPHQL)
            ->assertHeader('Cache-Control', 'max-age=5, private');
    }

    /** @dataProvider typeLevelCacheDataProvider */
    #[DataProvider('typeLevelCacheDataProvider')]
    public function testTypeLevelCache(string $query, string $expectedHeaderString): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            tasks: [Task!]! @hasMany
            tasksWithCache: [Task!]! @hasMany(relation: "tasks") @cacheControl(maxAge: 20)
        }

        type Task @cacheControl(maxAge: 10) {
            id: Int
            foo: String
            bar: String
        }

        type Query {
            user: User @first @cacheControl(maxAge: 50, scope: PRIVATE)
        }
        GRAPHQL;

        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $tasks = factory(Task::class, 3)->make();
        $user->tasks()->saveMany($tasks);

        $this->graphQL($query)
            ->assertHeader('Cache-Control', $expectedHeaderString);
    }

    /** @return iterable<array{string, string}> */
    public static function typeLevelCacheDataProvider(): iterable
    {
        yield [/** @lang GraphQL */ <<<'GRAPHQL'
            {
                user {
                    tasks {
                        id
                        foo
                    }
                }
            }
        GRAPHQL,
            'max-age=10, private',
        ];

        yield [/** @lang GraphQL */ <<<'GRAPHQL'
            {
                user {
                    tasksWithCache {
                        id
                        foo
                    }
                }
            }
        GRAPHQL,
            'max-age=20, private',
        ];
    }
}
