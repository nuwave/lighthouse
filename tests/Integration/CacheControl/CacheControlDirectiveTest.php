<?php declare(strict_types=1);

namespace Tests\Integration\CacheControl;

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

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            name: String
        }

        type Query {
            user: User @mock
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                name
            }
        }
        ')->assertHeader('Cache-Control', 'no-cache, private');
    }

    public function testInheritance(): void
    {
        $this->mockResolver([
            'id' => 1,
        ]);

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
        }

        type Query {
            me: User @mock @cacheControl(maxAge: 5, scope: PRIVATE)
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            me {
                id
            }
        }
        ')->assertHeader('Cache-Control', 'max-age=5, private');
    }

    /** @dataProvider rootScalarDataProvider */
    public function testRootScalar(string $query, string $expectedHeaderString): void
    {
        $this->mockResolver(1);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            default: ID @mock
            withDirective: ID @mock @cacheControl(maxAge: 5)
        }
        ';

        $this->graphQL($query)
            ->assertHeader('Cache-Control', $expectedHeaderString);
    }

    /** @return iterable<array{string, string}> */
    public static function rootScalarDataProvider(): iterable
    {
        yield [/** @lang GraphQL */ '
            {
                default
            }
            ',
            'no-cache, private',
        ];
        yield [/** @lang GraphQL */ '
            {
                withDirective
            }
            ',
            'max-age=5, public',
        ];
        yield [/** @lang GraphQL */ '
            {
                default
                withDirective
            }
            ',
            'no-cache, private',
        ];
    }

    public function testInheritanceWithNonScalar(): void
    {
        $this->mockResolver([
            'id' => 1,
            'self' => null,
        ]);

        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            child: User
        }

        type Query {
            me: User @mock @cacheControl(maxAge: 5, scope: PRIVATE)
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            me {
                child {
                    id
                }
            }
        }
        ')->assertHeader('Cache-Control', 'no-cache, private');
    }

    /** @dataProvider argumentsDataProvider */
    public function testDirectiveArguments(string $directive, string $expectedHeaderString): void
    {
        $this->mockResolver([
            'id' => 1,
            'name' => 'foobar',
        ]);

        $this->schema = /** @lang GraphQL */ "
        type User {
            id: ID!
            name: String {$directive}
        }

        type Query {
            user: User @mock @cacheControl(maxAge:50)
        }
        ";

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                name
            }
        }
        ')->assertHeader('Cache-Control', $expectedHeaderString);
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
    public function testUseDirectiveNested(string $query, string $expectedHeaderString): void
    {
        $this->schema = /** @lang GraphQL */ '
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
        ';

        $user = factory(User::class)->create();
        assert($user instanceof User);

        $tasks = factory(Task::class, 3)->make();
        $user->tasks()->saveMany($tasks);

        $posts = factory(Post::class, 3)->make();
        $user->posts()->saveMany($posts);

        $team = factory(Team::class)->create();
        assert($team instanceof Team);

        $users = factory(User::class, 3)->make();
        $team->users()->saveMany($users);

        $this->graphQL($query)
            ->assertHeader('Cache-Control', $expectedHeaderString);
    }

    /** @return iterable<array{string, string}> */
    public static function nestedQueryDataProvider(): iterable
    {
        yield [/** @lang GraphQL */ '
            {
                user {
                    tasks {
                        id
                        foo
                    }
                }
            }
            ',
            'max-age=5, private',
        ];
        yield [/** @lang GraphQL */ '
            {
                user {
                    tasks {
                        id
                    }
                }
            }
            ',
            'max-age=5, private',
        ];
        yield [/** @lang GraphQL */ '
            {
                team {
                    users {
                        tasks  {
                            id
                        }
                    }
                }
            }
            ',
            'no-cache, public',
        ];
        yield [/** @lang GraphQL */ '
            {
                team {
                    users {
                        tasks  {
                            foo
                        }
                    }
                }
            }
            ',
            'no-cache, public',
        ];
        yield [/** @lang GraphQL */ '
            {
                teamWithCache {
                    users {
                        tasks  {
                            bar
                        }
                    }
                }
            }
            ',
            'max-age=20, public',
        ];
        yield [/** @lang GraphQL */ '
            {
                teamWithCache {
                    users {
                        posts  {
                            id
                        }
                    }
                }
            }
            ',
            'no-cache, public',
        ];
        yield [/** @lang GraphQL */ '
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
            ',
            'no-cache, private',
        ];
    }

    public function testUsePaginate(): void
    {
        $this->schema = /** @lang GraphQL */ '
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
        ';

        $user = factory(User::class)->create();
        assert($user instanceof User);

        $tasks = factory(Task::class, 3)->make();
        $user->tasks()->saveMany($tasks);

        $this->graphQL(/** @lang GraphQL */ '
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
            ')
            ->assertHeader('Cache-Control', 'max-age=5, private');
    }

    /** @dataProvider typeLevelCacheDataProvider */
    public function testTypeLevelCache(string $query, string $expectedHeaderString): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            tasks: [Task!]! @hasMany
            tasksWithCache: [Task!]! @hasMany(relation: "tasks") @cacheControl(maxAge: 20)
        }

        type Task @cacheControl(maxAge: 10){
            id: Int
            foo: String
            bar: String
        }

        type Query {
            user: User @first @cacheControl(maxAge: 50, scope: PRIVATE)
        }
        ';

        $user = factory(User::class)->create();
        assert($user instanceof User);

        $tasks = factory(Task::class, 3)->make();
        $user->tasks()->saveMany($tasks);

        $this->graphQL($query)
            ->assertHeader('Cache-Control', $expectedHeaderString);
    }

    /** @return iterable<array{string, string}> */
    public static function typeLevelCacheDataProvider(): iterable
    {
        yield [/** @lang GraphQL */ '
            {
                user {
                    tasks {
                        id
                        foo
                    }
                }
            }
            ',
            'max-age=10, private',
        ];

        yield [/** @lang GraphQL */ '
            {
                user {
                    tasksWithCache {
                        id
                        foo
                    }
                }
            }
            ',
            'max-age=20, private',
        ];
    }
}
