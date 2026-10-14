<?php declare(strict_types=1);

namespace Tests\Integration\Select;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Schema\DirectiveLocator;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Tests\DBTestCase;
use Tests\Utils\Models\Comment;
use Tests\Utils\Models\Company;
use Tests\Utils\Models\Image;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Role;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;
use Tests\Utils\Models\User\UserBuilder;

final class SelectDirectiveTest extends DBTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::enableQueryLog();

        // @phpstan-ignore function.alreadyNarrowedType (available since Laravel 9.35)
        if (method_exists(Model::class, 'preventAccessingMissingAttributes')) {
            Model::preventAccessingMissingAttributes();
        }
    }

    protected function tearDown(): void
    {
        // @phpstan-ignore function.alreadyNarrowedType (available since Laravel 9.35)
        if (method_exists(Model::class, 'preventAccessingMissingAttributes')) {
            Model::preventAccessingMissingAttributes(false);
        }

        parent::tearDown();
    }

    public function testSelectsColumnsOfQueriedFields(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            users: [User!]! @all
        }

        type User {
            id: ID! @select(columns: ["id"])
            name: String @select(columns: ["name"])
            email: String @select(columns: ["email"])
        }
        GRAPHQL;

        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);
        DB::flushQueryLog();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users {
                name
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    ['name' => $user->name],
                ],
            ],
        ]);

        $this->assertSame(['select `users`.`id`, `users`.`name` from `users`'], $this->executedQueries());
    }

    public function testSelectsAllColumnsOfTypesWithoutSelect(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            users: [User!]! @all
        }

        type User {
            name: String
        }
        GRAPHQL;

        factory(User::class)->create();
        DB::flushQueryLog();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users {
                name
            }
        }
        ')->assertGraphQLErrorFree();

        $this->assertSame(['select * from `users`'], $this->executedQueries());
    }

    public function testSelectsTypeColumnsAndMultipleFieldColumnsOnce(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            users: [User!]! @all
        }

        type User @select(columns: ["email"]) {
            contact: String @select(columns: ["name", "email"]) @rename(attribute: "email")
            name: String @select(columns: ["name"])
        }
        GRAPHQL;

        factory(User::class)->create();
        DB::flushQueryLog();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users {
                name
                contact
                __typename
            }
        }
        ')->assertGraphQLErrorFree();

        $this->assertSame(['select `users`.`id`, `users`.`email`, `users`.`name` from `users`'], $this->executedQueries());
    }

    public function testSelectsParentKeysOfRelations(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            users: [User!]! @all
        }

        type User {
            id: ID! @select(columns: ["id"])
            company: Company @belongsTo
            tasks: [Task!]! @hasMany
            tasksCount: Int! @count(relation: "tasks")
        }

        type Company {
            name: String!
        }

        type Task {
            name: String!
        }
        GRAPHQL;

        $company = factory(Company::class)->create();
        $this->assertInstanceOf(Company::class, $company);

        $user = factory(User::class)->make();
        $this->assertInstanceOf(User::class, $user);
        $user->company()->associate($company);
        $user->save();

        $task = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $task);
        $task->user()->associate($user);
        $task->save();
        DB::flushQueryLog();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users {
                company {
                    name
                }
                tasks {
                    name
                }
                tasksCount
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    [
                        'company' => ['name' => $company->name],
                        'tasks' => [
                            ['name' => $task->name],
                        ],
                        'tasksCount' => 1,
                    ],
                ],
            ],
        ]);

        $this->assertSame('select `users`.`id`, `users`.`company_id` from `users`', $this->executedQueries()[0]);
    }

    public function testSelectsForeignKeyAndTypeOfMorphTo(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            images: [Image!]! @all
        }

        type Image {
            id: ID! @select(columns: ["id"])
            imageable: Task @morphTo
        }

        type Task {
            name: String!
        }
        GRAPHQL;

        $task = factory(Task::class)->create();
        $this->assertInstanceOf(Task::class, $task);

        $image = factory(Image::class)->make();
        $this->assertInstanceOf(Image::class, $image);
        $image->imageable()->associate($task);
        $image->save();
        DB::flushQueryLog();

        $this->graphQL(/** @lang GraphQL */ '
        {
            images {
                imageable {
                    name
                }
            }
        }
        ')->assertExactJson([
            'data' => [
                'images' => [
                    [
                        'imageable' => ['name' => $task->name],
                    ],
                ],
            ],
        ]);

        $this->assertSame('select `images`.`id`, `images`.`imageable_id`, `images`.`imageable_type` from `images`', $this->executedQueries()[0]);
    }

    public function testSelectsColumnsWithFind(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            user(id: ID! @eq): User @find
        }

        type User {
            name: String @select(columns: ["name"])
        }
        GRAPHQL;

        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);
        DB::flushQueryLog();

        $this->graphQL(/** @lang GraphQL */ '
        query ($id: ID!) {
            user(id: $id) {
                name
            }
        }
        ', [
            'id' => $user->id,
        ])->assertExactJson([
            'data' => [
                'user' => ['name' => $user->name],
            ],
        ]);

        $this->assertSame(['select `users`.`id`, `users`.`name` from `users` where `id` = ?'], $this->executedQueries());
    }

    public function testSelectsColumnsWithFirst(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            user: User @first
        }

        type User {
            name: String @select(columns: ["name"])
        }
        GRAPHQL;

        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);
        DB::flushQueryLog();

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                name
            }
        }
        ')->assertExactJson([
            'data' => [
                'user' => ['name' => $user->name],
            ],
        ]);

        $this->assertSame(['select `users`.`id`, `users`.`name` from `users` limit 1'], $this->executedQueries());
    }

    public function testSelectsColumnsWithPaginator(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            users: [User!]! @paginate
        }

        type User {
            name: String @select(columns: ["name"])
        }
        GRAPHQL;

        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);
        DB::flushQueryLog();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(first: 10) {
                data {
                    name
                }
                paginatorInfo {
                    total
                }
            }
        }
        ')->assertJson([
            'data' => [
                'users' => [
                    'data' => [
                        ['name' => $user->name],
                    ],
                    'paginatorInfo' => ['total' => 1],
                ],
            ],
        ]);

        $this->assertSame('select `users`.`id`, `users`.`name` from `users` limit 10 offset 0', Arr::last($this->executedQueries()));
    }

    public function testSelectsColumnsWithConnection(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            users: [User!]! @paginate(type: CONNECTION)
        }

        type User {
            name: String @select(columns: ["name"])
        }
        GRAPHQL;

        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);
        DB::flushQueryLog();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(first: 10) {
                edges {
                    node {
                        name
                    }
                }
            }
        }
        ')->assertJson([
            'data' => [
                'users' => [
                    'edges' => [
                        [
                            'node' => ['name' => $user->name],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame('select `users`.`id`, `users`.`name` from `users` limit 11 offset 0', Arr::last($this->executedQueries()));
    }

    public function testSelectsParentKeysOfMoreRelationKinds(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            task(id: ID! @eq): Task @find
        }

        type Task {
            name: String! @select(columns: ["name"])
            firstPost: Post @hasOne(relation: "post")
            postComments: [Comment!]! @hasManyThrough
            image: Image @morphOne
            user: User @belongsTo
        }

        type Post {
            title: String
        }

        type Comment {
            comment: String
        }

        type Image {
            url: String
        }

        type User {
            roles: [Role!]! @belongsToMany
        }

        type Role {
            name: String
        }
        GRAPHQL;

        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $role = factory(Role::class)->create();
        $this->assertInstanceOf(Role::class, $role);
        $user->roles()->attach($role);

        $task = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $task);
        $task->user()->associate($user);
        $task->save();

        $post = factory(Post::class)->make();
        $this->assertInstanceOf(Post::class, $post);
        $post->task()->associate($task);
        $post->user()->associate($user);
        $post->save();

        $comment = factory(Comment::class)->make();
        $this->assertInstanceOf(Comment::class, $comment);
        $comment->post()->associate($post);
        $comment->user()->associate($user);
        $comment->save();

        $image = factory(Image::class)->make();
        $this->assertInstanceOf(Image::class, $image);
        $image->imageable()->associate($task);
        $image->save();
        DB::flushQueryLog();

        $this->graphQL(/** @lang GraphQL */ '
        query ($id: ID!) {
            task(id: $id) {
                name
                firstPost {
                    title
                }
                postComments {
                    comment
                }
                image {
                    url
                }
                user {
                    roles {
                        name
                    }
                }
            }
        }
        ', [
            'id' => $task->id,
        ])->assertExactJson([
            'data' => [
                'task' => [
                    'name' => $task->name,
                    'firstPost' => ['title' => $post->title],
                    'postComments' => [
                        ['comment' => $comment->comment],
                    ],
                    'image' => ['url' => $image->url],
                    'user' => [
                        'roles' => [
                            ['name' => $role->name],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame('select `tasks`.`id`, `tasks`.`name`, `tasks`.`user_id` from `tasks` where `id` = ? and `tasks`.`deleted_at` is null and `name` != ?', $this->executedQueries()[0]);
    }

    public function testSelectsParentKeyOfNestedWithRelation(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            user(id: ID! @eq): User @find
        }

        type User {
            postsCommentsLoaded: Boolean! @with(relation: "posts.comments") @method @select(columns: [])
        }
        GRAPHQL;

        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $post = factory(Post::class)->make();
        $this->assertInstanceOf(Post::class, $post);
        $post->user()->associate($user);
        $post->save();
        DB::flushQueryLog();

        $this->graphQL(/** @lang GraphQL */ '
        query ($id: ID!) {
            user(id: $id) {
                postsCommentsLoaded
            }
        }
        ', [
            'id' => $user->id,
        ])->assertExactJson([
            'data' => [
                'user' => ['postsCommentsLoaded' => true],
            ],
        ]);

        $this->assertSame('select `users`.`id` from `users` where `id` = ?', $this->executedQueries()[0]);
    }

    public function testSelectsCacheKeyColumnsForCachedFields(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            users: [User!]! @all
        }

        type User {
            email: String! @cacheKey @select(columns: ["email"])
            name: String @cache @select(columns: ["name"])
        }
        GRAPHQL;

        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);
        DB::flushQueryLog();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users {
                name
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    ['name' => $user->name],
                ],
            ],
        ]);

        $this->assertSame(['select `users`.`id`, `users`.`name`, `users`.`email` from `users`'], $this->executedQueries());
    }

    public function testSelectsColumnsWithSimplePaginator(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            users: [User!]! @paginate(type: SIMPLE)
        }

        type User {
            name: String @select(columns: ["name"])
        }
        GRAPHQL;

        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);
        DB::flushQueryLog();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(first: 10) {
                data {
                    name
                }
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    'data' => [
                        ['name' => $user->name],
                    ],
                ],
            ],
        ]);

        $this->assertSame(['select `users`.`id`, `users`.`name` from `users` limit 11 offset 0'], $this->executedQueries());
    }

    public function testSelectsParentKeysOfEagerLoadedRelations(): void
    {
        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        type Query {
            users: [User!]! @all(builder: "{$this->qualifyTestResolver('usersWithCompany')}")
        }

        type User {
            name: String @select(columns: ["name"])
        }
        GRAPHQL;

        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);
        DB::flushQueryLog();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users {
                name
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    ['name' => $user->name],
                ],
            ],
        ]);

        $this->assertSame('select `users`.`id`, `users`.`name`, `users`.`company_id` from `users`', $this->executedQueries()[0]);
    }

    public function testKeepsColumnsSelectedByBuilder(): void
    {
        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        type Query {
            users: [User!]! @all(builder: "{$this->qualifyTestResolver('usersSelectingName')}")
        }

        type User {
            name: String @select(columns: ["name"])
        }
        GRAPHQL;

        factory(User::class)->create();
        DB::flushQueryLog();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users {
                name
            }
        }
        ')->assertGraphQLErrorFree();

        $this->assertSame(['select `name` from `users`'], $this->executedQueries());
    }

    public function testSelectsAllColumnsForInterfaces(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            users: [Named!]! @all(model: "User")
        }

        interface Named {
            name: String
        }

        type User implements Named {
            name: String @select(columns: ["name"])
        }
        GRAPHQL;

        factory(User::class)->create();
        DB::flushQueryLog();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users {
                name
            }
        }
        ')->assertGraphQLErrorFree();

        $this->assertSame(['select * from `users`'], $this->executedQueries());
    }

    public function testKeepsSubqueryBindingsOfOrderByRelationCount(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            users(
                orderBy: _ @orderBy(relations: [{ relation: "tasks" }])
            ): [User!]! @all
        }

        type User {
            id: Int! @select(columns: ["id"])
        }
        GRAPHQL;

        $userA = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $userA);

        $taskOfA = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $taskOfA);
        $taskOfA->user()->associate($userA);
        $taskOfA->save();

        $userB = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $userB);

        $firstTaskOfB = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $firstTaskOfB);
        $firstTaskOfB->user()->associate($userB);
        $firstTaskOfB->save();

        $secondTaskOfB = factory(Task::class)->make();
        $this->assertInstanceOf(Task::class, $secondTaskOfB);
        $secondTaskOfB->user()->associate($userB);
        $secondTaskOfB->save();
        DB::flushQueryLog();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(orderBy: [{ tasks: { aggregate: COUNT }, order: DESC }]) {
                id
            }
        }
        ')->assertExactJson([
            'data' => [
                'users' => [
                    ['id' => $userB->id],
                    ['id' => $userA->id],
                ],
            ],
        ]);

        $this->assertSame(
            ['select `users`.`id`, (select count(*) from `tasks` where `users`.`id` = `tasks`.`user_id` and `tasks`.`deleted_at` is null and `name` != ?) as `tasks_count` from `users` order by `tasks_count` desc'],
            $this->executedQueries(),
        );
    }

    public function testIgnoresOtherDirectivesNamedSelect(): void
    {
        $directive = new class extends BaseDirective {
            public static function definition(): string
            {
                return /** @lang GraphQL */ 'directive @select(fields: [String!]) on FIELD_DEFINITION';
            }
        };
        $directiveLocator = $this->app->make(DirectiveLocator::class);
        $directiveLocator->setResolved('select', $directive::class);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            users: [User!]! @all
        }

        type User {
            name: String @select(fields: ["name"])
            email: String
        }
        GRAPHQL;

        factory(User::class)->create();
        DB::flushQueryLog();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users {
                name
            }
        }
        ')->assertGraphQLErrorFree();

        $this->assertSame(['select * from `users`'], $this->executedQueries());
    }

    /** @return array<int, string> */
    protected function executedQueries(): array
    {
        return array_column(DB::getQueryLog(), 'query');
    }

    public static function usersWithCompany(): UserBuilder
    {
        return User::query()->with('company');
    }

    public static function usersSelectingName(): UserBuilder
    {
        return User::query()->select('name');
    }
}
