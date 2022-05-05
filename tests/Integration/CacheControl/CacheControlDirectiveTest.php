<?php

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

        $this->schema /** @lang GraphQL */ = '
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

        $this->schema /** @lang GraphQL */ = '
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

    public function testInheritanceWithNonScalar(): void
    {
        $this->mockResolver([
            'id' => 1,
            'self' => null,
        ]);

        $this->schema /** @lang GraphQL */ = '
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

    /**
     * @dataProvider argumentsDataProvider
     */
    public function testDirectiveArguments(string $directive, string $expectedHeaderString): void
    {
        $this->mockResolver([
            'id' => 1,
            'name' => 'foobar',
        ]);

        $this->schema /** @lang GraphQL */ = "
        type User {
            id: ID!
            name: String $directive
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

    /**
     * @return array<string, array{string, string}>
     */
    public function argumentsDataProvider(): array
    {
        return [
            'noArguments' => ['@cacheControl', 'no-cache, public'],
            'onlyMaxAge' => ['@cacheControl(maxAge: 10)', 'max-age=10, public'],
            'onlyScope' => ['@cacheControl(scope: PRIVATE)', 'no-cache, private'],
            'allSet' => ['@cacheControl(maxAge:10, scope: PRIVATE)', 'max-age=10, private'],
        ];
    }

    /**
     * @dataProvider nestedQueryDataProvider
     */
    public function testUseDirectiveNested(string $query, string $expectedHeaderString): void
    {
        $this->schema /** @lang GraphQL */ = '
        type User {
            tasks: [Task!]! @hasMany @cacheControl(maxAge: 50, scope: PUBLIC)
            posts: [Post!]! @hasMany
        }

        type Post {
            id: Int @cacheControl(maxAge: 10, scope: PUBLIC)
            foo: String @cacheControl(scope: PRIVATE)
        }

        type Team {
            users: [User!]! @hasMany @cacheControl(maxAge: 25, scope: PUBLIC)
        }

        type Task {
            id: Int @cacheControl(maxAge: 10, scope: PUBLIC)
            foo: String @cacheControl
            bar: String
        }

        type Query {
            user: User @first @cacheControl(maxAge: 5, scope: PRIVATE)
            team: Team @first
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

        $this->graphQL($query)->assertHeader('Cache-Control', $expectedHeaderString);
    }

    /**
     * @return array<int, array{string, string}>
     */
    public function nestedQueryDataProvider(): array
    {
        return [
            [/** @lang GraphQL */ '
                {
                    user {
                        tasks {
                            id
                            foo
                        }
                    }
                }
            ', 'no-cache, private',
            ],
            [/** @lang GraphQL */ '
                {
                    user {
                        tasks {
                            id
                        }
                    }
                }
            ', 'max-age=5, private',
            ],
            [/** @lang GraphQL */ '
                {
                    team {
                        users {
                            tasks  {
                                id
                            }
                        }
                    }
                }
            ', 'no-cache, public',
            ],
            [/** @lang GraphQL */ '
                {
                    team {
                        users {
                            tasks  {
                                foo
                            }
                        }
                    }
                }
            ', 'no-cache, public',
            ],
            [/** @lang GraphQL */ '
                {
                    teamWithCache {
                        users {
                            tasks  {
                                bar
                            }
                        }
                    }
                }
            ', 'max-age=20, public',
            ],
            [/** @lang GraphQL */ '
                {
                    teamWithCache {
                        users {
                            posts  {
                                id
                            }
                        }
                    }
                }
            ', 'no-cache, public',
            ],
            [/** @lang GraphQL */ '
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
            ', 'no-cache, private',
            ],
        ];
    }
}
