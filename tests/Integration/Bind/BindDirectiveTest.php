<?php declare(strict_types=1);

namespace Tests\Integration\Bind;

use Closure;
use GraphQL\Error\Error;
use Illuminate\Database\MultipleRecordsFoundException;
use Nuwave\Lighthouse\Bind\BindDefinition;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Testing\MocksResolvers;
use Nuwave\Lighthouse\Testing\UsesTestSchema;
use Tests\DBTestCase;
use Tests\Utils\Bind\SpyCallableClassBinding;
use Tests\Utils\Models\Company;
use Tests\Utils\Models\User;
use Tests\Utils\Resolvers\SpyResolver;

use function factory;
use function trans;

final class BindDirectiveTest extends DBTestCase
{
    use UsesTestSchema;
    use MocksResolvers;

    public function testSchemaValidationFailsWhenClassArgumentDefinedOnFieldArgumentIsNotAClass(): void
    {
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
            }

            type Query {
                user(user: ID! @bind(class: "NotAClass")): User! @mock
            }
            GRAPHQL;

        $this->expectExceptionObject(new DefinitionException(
            '@bind argument `class` defined on `user.user` must be an existing class, received `NotAClass`.'
        ));

        $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            query {
                user(user: "1") {
                    id
                }
            }
            GRAPHQL);
    }

    public function testSchemaValidationFailsWhenClassArgumentDefinedOnInputFieldIsNotAClass(): void
    {
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
            }

            input RemoveUsersInput {
                users: [ID!]! @bind(class: "NotAClass")
            }

            type Mutation {
                removeUsers(input: RemoveUsersInput!): Boolean! @mock
            }
            GRAPHQL;

        $this->expectExceptionObject(new DefinitionException(
            '@bind argument `class` defined on `RemoveUsersInput.users` must be an existing class, received `NotAClass`.'
        ));

        $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            mutation ($input: RemoveUsersInput!) {
                removeUsers(input: $input)
            }
            GRAPHQL,
            [
                'input' => [
                    'users' => ['1'],
                ],
            ],
        );
    }

    public function testSchemaValidationFailsWhenClassArgumentDefinedOnFieldArgumentIsNotAModelOrCallableClass(): void
    {
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
            }

            type Query {
                user(user: ID! @bind(class: "stdClass")): User! @mock
            }
            GRAPHQL;

        $this->expectExceptionObject(new DefinitionException(
            '@bind argument `class` defined on `user.user` must be an ' .
            'Eloquent model or a callable class, received `stdClass`.'
        ));

        $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            query {
                user(user: "1") {
                    id
                }
            }
            GRAPHQL);
    }

    public function testSchemaValidationFailsWhenClassArgumentDefinedOnInputFieldIsNotAModelOrCallableClass(): void
    {
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
            }

            input RemoveUsersInput {
                users: [ID!]! @bind(class: "stdClass")
            }

            type Mutation {
                removeUsers(input: RemoveUsersInput!): Boolean! @mock
            }
            GRAPHQL;

        $this->expectExceptionObject(new DefinitionException(
            '@bind argument `class` defined on `RemoveUsersInput.users` must be an ' .
            'Eloquent model or a callable class, received `stdClass`.'
        ));

        $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            mutation ($input: RemoveUsersInput!) {
                removeUsers(input: $input)
            }
            GRAPHQL,
            [
                'input' => [
                    'users' => ['1'],
                ],
            ],
        );
    }

    public function testSchemaValidationFailsWhenValueTypeDefinedOnFieldArgumentIsNotSupported(): void
    {
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
                type: UserType!
            }
            
            enum UserType {
                ADMINISTRATOR
                MODERATOR
            }

            type Query {
                usersByType(type: UserType! @bind(class: "Tests\\Utils\\Models\\User")): User! @mock
            }
            GRAPHQL;

        $this->expectExceptionObject(new DefinitionException(
            '@bind directive defined on `usersByType.type` does not support value of type `UserType`. ' .
            'Expected `ID`, `String`, `Int` or a list of one of these types.'
        ));

        $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            query ($type: UserType!) {
                usersByType(type: $type) {
                    id
                }
            }
            GRAPHQL,
            ['type' => 'ADMINISTRATOR'],
        );
    }

    public function testSchemaValidationFailsWhenValueTypeDefinedOnInputFieldIsNotSupported(): void
    {
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
                type: UserType!
            }
            
            enum UserType {
                ADMINISTRATOR
                MODERATOR
            }
            
            input UsersByTypeInput {
                type: UserType! @bind(class: "Tests\\Utils\\Models\\User")
            }

            type Query {
                usersByType(input: UsersByTypeInput!): User! @mock
            }
            GRAPHQL;

        $this->expectExceptionObject(new DefinitionException(
            '@bind directive defined on `UsersByTypeInput.type` does not support value of type `UserType`. ' .
            'Expected `ID`, `String`, `Int` or a list of one of these types.'
        ));

        $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            query ($input: UsersByTypeInput!) {
                usersByType(input: $input) {
                    id
                }
            }
            GRAPHQL,
            [
                'input' => [
                    'type' => 'ADMINISTRATOR',
                ],
            ],
        );
    }

    public function testModelBindingOnFieldArgument(): void
    {
        $user = factory(User::class)->create();
        $this->mockResolver(fn (mixed $root, array $args): User => $args['user']);
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
            }

            type Query {
                user(user: ID! @bind(class: "Tests\\Utils\\Models\\User")): User! @mock
            }
            GRAPHQL;

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            query ($id: ID!) {
                user(user: $id) {
                    id
                }
            }
            GRAPHQL,
            ['id' => $user->getKey()],
        );

        $response->assertGraphQLErrorFree();
        $response->assertJson([
            'data' => [
                'user' => [
                    'id' => $user->getKey(),
                ],
            ],
        ]);
    }

    public function testMissingModelBindingOnFieldArgument(): void
    {
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
            }

            type Query {
                user(user: ID! @bind(class: "Tests\\Utils\\Models\\User")): User! @mock
            }
            GRAPHQL;

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            query {
                user(user: "1") {
                    id
                }
            }
            GRAPHQL);

        $response->assertOk();
        $response->assertGraphQLValidationError('user', trans('validation.exists', ['attribute' => 'user']));
    }

    public function testMissingOptionalModelBindingOnFieldArgument(): void
    {
        $this->mockResolver(fn (mixed $root, array $args) => $args['user']);
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
            }

            type Query {
                user(
                    user: ID! @bind(class: "Tests\\Utils\\Models\\User", required: false)
                ): User @mock
            }
            GRAPHQL;

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            query ($id: ID!) {
                user(user: $id) {
                    id
                }
            }
            GRAPHQL,
            ['id' => '1'],
        );

        $response->assertGraphQLErrorFree();
        $response->assertJson([
            'data' => [
                'user' => null,
            ],
        ]);
    }

    public function testModelBindingByColumnOnFieldArgument(): void
    {
        $user = factory(User::class)->create();
        $this->mockResolver(fn (mixed $root, array $args) => $args['user']);
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
            }

            type Query {
                user(
                    user: String! @bind(class: "Tests\\Utils\\Models\\User", column: "email")
                ): User @mock
            }
            GRAPHQL;

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            query ($email: String!) {
                user(user: $email) {
                    id
                }
            }
            GRAPHQL,
            ['email' => $user->email],
        );

        $response->assertGraphQLErrorFree();
        $response->assertJson([
            'data' => [
                'user' => [
                    'id' => $user->getKey(),
                ],
            ],
        ]);
    }

    public function testModelBindingWithEagerLoadingOnFieldArgument(): void
    {
        $user = factory(User::class)->create();
        $resolver = new SpyResolver(fn (mixed $root, array $args) => $args['user']);
        $this->mockResolver($resolver);
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
            }

            type Query {
                user(
                    user: ID! @bind(class: "Tests\\Utils\\Models\\User", with: ["company"])
                ): User @mock
            }
            GRAPHQL;

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            query ($id: ID!) {
                user(user: $id) {
                    id
                }
            }
            GRAPHQL,
            ['id' => $user->getKey()],
        );

        $response->assertGraphQLErrorFree();
        $response->assertJson([
            'data' => [
                'user' => [
                    'id' => $user->getKey(),
                ],
            ],
        ]);
        $resolver->assertArgs(function (array $args): void {
            $this->assertInstanceOf(User::class, $args['user']);
            $this->assertTrue($args['user']->relationLoaded('company'));
        });
    }

    public function testModelBindingWithTooManyResultsOnFieldArgument(): void
    {
        $this->rethrowGraphQLErrors();
        $users = factory(User::class, 2)->create(['name' => 'John Doe']);
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
            }

            type Query {
                user(
                    user: String! @bind(class: "Tests\\Utils\\Models\\User", column: "name")
                ): User @mock
            }
            GRAPHQL;

        $makeRequest = fn () => $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            query ($name: String!) {
                user(user: $name) {
                    id
                }
            }
            GRAPHQL,
            ['name' => $users->first()->name],
        );

        $this->assertThrowsMultipleRecordsFoundException($makeRequest, $users->count());
    }

    public function testModelCollectionBindingOnFieldArgument(): void
    {
        $users = factory(User::class, 2)->create();
        $resolver = new SpyResolver(return: true);
        $this->mockResolver($resolver);
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
            }

            type Mutation {
                removeUsers(
                    users: [ID!]! @bind(class: "Tests\\Utils\\Models\\User")
                ): Boolean! @mock
            }

            type Query {
                ping: Boolean
            }
            GRAPHQL;

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            mutation ($users: [ID!]!) {
                removeUsers(users: $users)
            }
            GRAPHQL,
            ['users' => $users->map(fn (User $user): int => $user->getKey())],
        );

        $response->assertGraphQLErrorFree();
        $response->assertJson([
            'data' => [
                'removeUsers' => true,
            ],
        ]);
        $resolver->assertArgs(function (array $args) use ($users): void {
            $this->assertArrayHasKey('users', $args);
            $this->assertCount($users->count(), $args['users']);
            $users->each(function (User $user) use ($args): void {
                $this->assertTrue($user->is($args['users'][$user->getKey()]));
            });
        });
    }

    public function testMissingModelCollectionBindingOnFieldArgument(): void
    {
        $user = factory(User::class)->create();
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
            }

            type Mutation {
                removeUsers(
                    users: [ID!]! @bind(class: "Tests\\Utils\\Models\\User")
                ): Boolean! @mock
            }

            type Query {
                ping: Boolean
            }
            GRAPHQL;

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            mutation ($users: [ID!]!) {
                removeUsers(users: $users)
            }
            GRAPHQL,
            [
                'users' => [$user->getKey(), '10'],
            ],
        );

        $response->assertOk();
        $response->assertGraphQLValidationError('users.1', trans('validation.exists', ['attribute' => 'users.1']));
    }

    public function testMissingOptionalModelCollectionBindingOnFieldArgument(): void
    {
        $user = factory(User::class)->create();
        $resolver = new SpyResolver(return: true);
        $this->mockResolver($resolver);
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
            }

            type Mutation {
                removeUsers(
                    users: [ID!]! @bind(class: "Tests\\Utils\\Models\\User", required: false)
                ): Boolean! @mock
            }

            type Query {
                ping: Boolean
            }
            GRAPHQL;

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            mutation ($users: [ID!]!) {
                removeUsers(users: $users)
            }
            GRAPHQL,
            [
                'users' => [$user->getKey(), '10'],
            ],
        );

        $response->assertGraphQLErrorFree();
        $response->assertJson([
            'data' => [
                'removeUsers' => true,
            ],
        ]);
        $resolver->assertArgs(function (array $args) use ($user): void {
            $this->assertArrayHasKey('users', $args);
            $this->assertCount(1, $args['users']);
            $this->assertTrue($user->is($args['users'][$user->getKey()]));
        });
    }

    public function testModelCollectionBindingWithTooManyResultsOnFieldArgument(): void
    {
        $this->rethrowGraphQLErrors();
        $users = factory(User::class, 2)->create(['name' => 'John Doe']);
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
            }

            type Mutation {
                removeUsers(
                    users: [String!]! @bind(class: "Tests\\Utils\\Models\\User", column: "name")
                ): Boolean! @mock
            }

            type Query {
                ping: Boolean
            }
            GRAPHQL;

        $makeRequest = fn () => $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            mutation ($users: [String!]!) {
                removeUsers(users: $users)
            }
            GRAPHQL,
            [
                'users' => [$users->first()->name],
            ],
        );

        $this->assertThrowsMultipleRecordsFoundException($makeRequest, $users->count());
    }

    public function testModelBindingOnInputField(): void
    {
        $user = factory(User::class)->create();
        $this->mockResolver(fn (mixed $root, array $args): User => $args['input']['user']);
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
            }

            input UserInput {
                user: ID! @bind(class: "Tests\\Utils\\Models\\User")
            }

            type Query {
                user(input: UserInput!): User! @mock
            }
            GRAPHQL;

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            query ($input: UserInput!) {
                user(input: $input) {
                    id
                }
            }
            GRAPHQL,
            [
                'input' => [
                    'user' => $user->getKey(),
                ],
            ],
        );

        $response->assertGraphQLErrorFree();
        $response->assertJson([
            'data' => [
                'user' => [
                    'id' => $user->getKey(),
                ],
            ],
        ]);
    }

    public function testMissingModelBindingOnInputField(): void
    {
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
            }

            input UserInput {
                user: ID! @bind(class: "Tests\\Utils\\Models\\User")
            }

            type Query {
                user(input: UserInput!): User! @mock
            }
            GRAPHQL;

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            query ($input: UserInput!) {
                user(input: $input) {
                    id
                }
            }
            GRAPHQL,
            [
                'input' => [
                    'user' => '1',
                ],
            ],
        );

        $response->assertOk();
        $response->assertGraphQLValidationError('input.user', trans('validation.exists', [
            'attribute' => 'input.user',
        ]));
    }

    public function testMissingOptionalModelBindingOnInputField(): void
    {
        $this->mockResolver(fn (mixed $root, array $args) => $args['input']['user']);
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
            }

            input UserInput {
                user: ID! @bind(class: "Tests\\Utils\\Models\\User", required: false)
            }

            type Query {
                user(input: UserInput!): User @mock
            }
            GRAPHQL;

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            query ($input: UserInput!) {
                user(input: $input) {
                    id
                }
            }
            GRAPHQL,
            [
                'input' => [
                    'user' => '1',
                ],
            ],
        );

        $response->assertGraphQLErrorFree();
        $response->assertJson([
            'data' => [
                'user' => null,
            ],
        ]);
    }

    public function testModelBindingByColumnOnInputField(): void
    {
        $user = factory(User::class)->create();
        $this->mockResolver(fn (mixed $root, array $args) => $args['input']['user']);
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
            }

            input UserInput {
                user: String! @bind(class: "Tests\\Utils\\Models\\User", column: "email")
            }

            type Query {
                user(input: UserInput!): User @mock
            }
            GRAPHQL;

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            query ($input: UserInput!) {
                user(input: $input) {
                    id
                }
            }
            GRAPHQL,
            [
                'input' => [
                    'user' => $user->email,
                ],
            ],
        );

        $response->assertGraphQLErrorFree();
        $response->assertJson([
            'data' => [
                'user' => [
                    'id' => $user->id,
                ],
            ],
        ]);
    }

    public function testModelBindingWithEagerLoadingOnInputField(): void
    {
        $user = factory(User::class)->create();
        $resolver = new SpyResolver(fn (mixed $root, array $args) => $args['input']['user']);
        $this->mockResolver($resolver);
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
            }

            input UserInput {
                user: ID! @bind(class: "Tests\\Utils\\Models\\User", with: ["company"])
            }

            type Query {
                user(input: UserInput!): User @mock
            }
            GRAPHQL;

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            query ($input: UserInput!) {
                user(input: $input) {
                    id
                }
            }
            GRAPHQL,
            [
                'input' => [
                    'user' => $user->getKey(),
                ],
            ],
        );

        $response->assertGraphQLErrorFree();
        $response->assertJson([
            'data' => [
                'user' => [
                    'id' => $user->id,
                ],
            ],
        ]);
        $resolver->assertArgs(function (array $args): void {
            $this->assertInstanceOf(User::class, $args['input']['user']);
            $this->assertTrue($args['input']['user']->relationLoaded('company'));
        });
    }

    public function testModelBindingWithTooManyResultsOnInputField(): void
    {
        $this->rethrowGraphQLErrors();
        $users = factory(User::class, 2)->create(['name' => 'Jane Doe']);
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
            }

            input UserInput {
                user: String! @bind(class: "Tests\\Utils\\Models\\User", column: "name")
            }

            type Query {
                user(input: UserInput!): User @mock
            }
            GRAPHQL;

        $makeRequest = fn () => $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            query ($input: UserInput!) {
                user(input: $input) {
                    id
                }
            }
            GRAPHQL,
            [
                'input' => [
                    'user' => $users->first()->name,
                ],
            ],
        );

        $this->assertThrowsMultipleRecordsFoundException($makeRequest, $users->count());
    }

    public function testModelCollectionBindingOnInputField(): void
    {
        $users = factory(User::class, 2)->create();
        $resolver = new SpyResolver(return: true);
        $this->mockResolver($resolver);
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
            }

            input RemoveUsersInput {
                users: [ID!]! @bind(class: "Tests\\Utils\\Models\\User")
            }

            type Mutation {
                removeUsers(input: RemoveUsersInput!): Boolean! @mock
            }

            type Query {
                ping: Boolean
            }
            GRAPHQL;

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            mutation ($input: RemoveUsersInput!) {
                removeUsers(input: $input)
            }
            GRAPHQL,
            [
                'input' => [
                    'users' => $users->map(fn (User $user): int => $user->getKey()),
                ],
            ],
        );

        $response->assertGraphQLErrorFree();
        $response->assertJson([
            'data' => [
                'removeUsers' => true,
            ],
        ]);
        $resolver->assertArgs(function (array $args) use ($users): void {
            $this->assertArrayHasKey('input', $args);
            $this->assertArrayHasKey('users', $args['input']);
            $this->assertCount($users->count(), $args['input']['users']);
            $users->each(function (User $user) use ($args): void {
                $this->assertTrue($user->is($args['input']['users'][$user->getKey()]));
            });
        });
    }

    public function testMissingModelCollectionBindingOnInputField(): void
    {
        $user = factory(User::class)->create();
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
            }

            input RemoveUsersInput {
                users: [ID!]! @bind(class: "Tests\\Utils\\Models\\User")
            }

            type Mutation {
                removeUsers(input: RemoveUsersInput!): Boolean! @mock
            }

            type Query {
                ping: Boolean
            }
            GRAPHQL;

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            mutation ($input: RemoveUsersInput!) {
                removeUsers(input: $input)
            }
            GRAPHQL,
            [
                'input' => [
                    'users' => [$user->getKey(), '10'],
                ],
            ],
        );

        $response->assertOk();
        $response->assertGraphQLValidationError('input.users.1', trans('validation.exists', [
            'attribute' => 'input.users.1',
        ]));
    }

    public function testMissingOptionalModelCollectionBindingOnInputField(): void
    {
        $user = factory(User::class)->create();
        $resolver = new SpyResolver(return: true);
        $this->mockResolver($resolver);
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
            }

            input RemoveUsersInput {
                users: [ID!]! @bind(class: "Tests\\Utils\\Models\\User", required: false)
            }

            type Mutation {
                removeUsers(input: RemoveUsersInput!): Boolean! @mock
            }

            type Query {
                ping: Boolean
            }
            GRAPHQL;

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            mutation ($input: RemoveUsersInput!) {
                removeUsers(input: $input)
            }
            GRAPHQL,
            [
                'input' => [
                    'users' => [$user->getKey(), '10'],
                ],
            ],
        );

        $response->assertGraphQLErrorFree();
        $response->assertJson([
            'data' => [
                'removeUsers' => true,
            ],
        ]);
        $resolver->assertArgs(function (array $args) use ($user): void {
            $this->assertArrayHasKey('input', $args);
            $this->assertArrayHasKey('users', $args['input']);
            $this->assertCount(1, $args['input']['users']);
            $this->assertTrue($user->is($args['input']['users'][$user->getKey()]));
        });
    }

    public function testModelCollectionBindingWithTooManyResultsOnInputField(): void
    {
        $this->rethrowGraphQLErrors();
        $users = factory(User::class, 2)->create(['name' => 'Jane Doe']);
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
            }

            input RemoveUsersInput {
                users: [String!]! @bind(class: "Tests\\Utils\\Models\\User", column: "name")
            }

            type Mutation {
                removeUsers(input: RemoveUsersInput!): Boolean! @mock
            }

            type Query {
                ping: Boolean
            }
            GRAPHQL;

        $makeRequest = fn () => $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            mutation ($input: RemoveUsersInput!) {
                removeUsers(input: $input)
            }
            GRAPHQL,
            [
                'input' => [
                    'users' => [$users->first()->name],
                ],
            ],
        );

        $this->assertThrowsMultipleRecordsFoundException($makeRequest, $users->count());
    }

    public function testCallableClassBindingOnFieldArgument(): void
    {
        $user = factory(User::class)->make(['id' => 1]);
        $this->instance(SpyCallableClassBinding::class, new SpyCallableClassBinding($user));
        $this->mockResolver(fn (mixed $root, array $args): User => $args['user']);
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
            }

            type Query {
                user(user: ID! @bind(class: "Tests\\Utils\\Bind\\SpyCallableClassBinding")): User! @mock
            }
            GRAPHQL;

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            query ($id: ID!) {
                user(user: $id) {
                    id
                }
            }
            GRAPHQL,
            ['id' => $user->getKey()],
        );

        $response->assertGraphQLErrorFree();
        $response->assertJson([
            'data' => [
                'user' => [
                    'id' => $user->getKey(),
                ],
            ],
        ]);
    }

    public function testMissingCallableClassBindingOnFieldArgument(): void
    {
        $this->instance(SpyCallableClassBinding::class, new SpyCallableClassBinding(null));
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
            }

            type Query {
                user(
                    user: ID! @bind(class: "Tests\\Utils\\Bind\\SpyCallableClassBinding")
                ): User @mock
            }
            GRAPHQL;

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            query ($id: ID!) {
                user(user: $id) {
                    id
                }
            }
            GRAPHQL,
            ['id' => '1'],
        );

        $response->assertOk();
        $response->assertGraphQLValidationError('user', trans('validation.exists', ['attribute' => 'user']));
    }

    public function testMissingOptionalCallableClassBindingOnFieldArgument(): void
    {
        $this->instance(SpyCallableClassBinding::class, new SpyCallableClassBinding(null));
        $this->mockResolver(fn (mixed $root, array $args) => $args['user']);
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
            }

            type Query {
                user(
                    user: ID! @bind(class: "Tests\\Utils\\Bind\\SpyCallableClassBinding", required: false)
                ): User @mock
            }
            GRAPHQL;

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            query ($id: ID!) {
                user(user: $id) {
                    id
                }
            }
            GRAPHQL,
            ['id' => '1'],
        );

        $response->assertGraphQLErrorFree();
        $response->assertJson([
            'data' => [
                'user' => null,
            ],
        ]);
    }

    public function testCallableClassBindingWithDirectiveArgumentsOnFieldArgument(): void
    {
        $callableClassBinding = new SpyCallableClassBinding(null);
        $this->instance(SpyCallableClassBinding::class, $callableClassBinding);
        $this->mockResolver(fn (mixed $root, array $args) => $args['user']);
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
            }

            type Query {
                user(
                    user: ID! @bind(
                        class: "Tests\\Utils\\Bind\\SpyCallableClassBinding"
                        column: "uid"
                        with: ["relation"]
                        required: false
                    )
                ): User @mock
            }
            GRAPHQL;

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            query ($id: ID!) {
                user(user: $id) {
                    id
                }
            }
            GRAPHQL,
            ['id' => '1'],
        );

        $response->assertGraphQLErrorFree();
        $response->assertJson([
            'data' => [
                'user' => null,
            ],
        ]);
        $callableClassBinding->assertCalledWith(
            '1',
            new BindDefinition(SpyCallableClassBinding::class, 'uid', ['relation'], false),
        );
    }

    public function testCallableClassBindingOnInputField(): void
    {
        $user = factory(User::class)->make(['id' => 1]);
        $this->instance(SpyCallableClassBinding::class, new SpyCallableClassBinding($user));
        $this->mockResolver(fn (mixed $root, array $args): User => $args['input']['user']);
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
            }
            
            input UserInput {
                user: ID! @bind(class: "Tests\\Utils\\Bind\\SpyCallableClassBinding")
            }

            type Query {
                user(input: UserInput!): User! @mock
            }
            GRAPHQL;

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            query ($input: UserInput!) {
                user(input: $input) {
                    id
                }
            }
            GRAPHQL,
            [
                'input' => [
                    'user' => $user->getKey(),
                ],
            ],
        );

        $response->assertGraphQLErrorFree();
        $response->assertJson([
            'data' => [
                'user' => [
                    'id' => $user->getKey(),
                ],
            ],
        ]);
    }

    public function testMissingCallableClassBindingOnInputField(): void
    {
        $this->instance(SpyCallableClassBinding::class, new SpyCallableClassBinding(null));
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
            }
            
            input UserInput {
                user: ID! @bind(class: "Tests\\Utils\\Bind\\SpyCallableClassBinding")
            }

            type Query {
                user(input: UserInput!): User! @mock
            }
            GRAPHQL;

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            query ($input: UserInput!) {
                user(input: $input) {
                    id
                }
            }
            GRAPHQL,
            [
                'input' => [
                    'user' => '1',
                ],
            ],
        );

        $response->assertOk();
        $response->assertGraphQLValidationError('input.user', trans('validation.exists', ['attribute' => 'input.user']));
    }

    public function testMissingOptionalCallableClassBindingOnInputField(): void
    {
        $this->instance(SpyCallableClassBinding::class, new SpyCallableClassBinding(null));
        $this->mockResolver(fn (mixed $root, array $args) => $args['input']['user']);
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
            }
            
            input UserInput {
                user: ID! @bind(class: "Tests\\Utils\\Bind\\SpyCallableClassBinding", required: false)
            }

            type Query {
                user(input: UserInput!): User @mock
            }
            GRAPHQL;

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            query ($input: UserInput!) {
                user(input: $input) {
                    id
                }
            }
            GRAPHQL,
            [
                'input' => [
                    'user' => '1',
                ],
            ],
        );

        $response->assertGraphQLErrorFree();
        $response->assertJson([
            'data' => [
                'user' => null,
            ],
        ]);
    }

    public function testCallableClassBindingWithDirectiveArgumentsOnInputField(): void
    {
        $callableClassBinding = new SpyCallableClassBinding(null);
        $this->instance(SpyCallableClassBinding::class, $callableClassBinding);
        $this->mockResolver(fn (mixed $root, array $args) => $args['input']['user']);
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
            }
            
            input UserInput {
                user: ID! @bind(
                    class: "Tests\\Utils\\Bind\\SpyCallableClassBinding"
                    column: "uid"
                    with: ["relation"]
                    required: false
                )
            }

            type Query {
                user(input: UserInput!): User @mock
            }
            GRAPHQL;

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            query ($input: UserInput!) {
                user(input: $input) {
                    id
                }
            }
            GRAPHQL,
            [
                'input' => [
                    'user' => '1',
                ],
            ],
        );

        $response->assertGraphQLErrorFree();
        $response->assertJson([
            'data' => [
                'user' => null,
            ],
        ]);
        $callableClassBinding->assertCalledWith(
            '1',
            new BindDefinition(SpyCallableClassBinding::class, 'uid', ['relation'], false),
        );
    }

    public function testMultipleBindingsInSameRequest(): void
    {
        $user = factory(User::class)->create();
        $company = factory(Company::class)->create();
        $resolver = new SpyResolver(return: true);
        $this->mockResolver($resolver);
        $this->schema =  /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
            }

            type Company {
                id: ID!
            }

            type Mutation {
                addUserToCompany(
                    user: ID! @bind(class: "Tests\\Utils\\Models\\User")
                    company: ID! @bind(class: "Tests\\Utils\\Models\\Company")
                ): Boolean! @mock
            }

            type Query {
                ping: Boolean
            }
            GRAPHQL;

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            mutation ($user: ID!, $company: ID!) {
                addUserToCompany(user: $user, company: $company)
            }
            GRAPHQL,
            [
                'user' => $user->getKey(),
                'company' => $company->getKey(),
            ],
        );

        $response->assertGraphQLErrorFree();
        $response->assertJson([
            'data' => [
                'addUserToCompany' => true,
            ],
        ]);
        $resolver->assertArgs(function (array $args) use ($user, $company): void {
            $this->assertArrayHasKey('user', $args);
            $this->assertTrue($user->is($args['user']));
            $this->assertArrayHasKey('company', $args);
            $this->assertTrue($company->is($args['company']));
        });
    }

    private function assertThrowsMultipleRecordsFoundException(Closure $makeRequest, int $count): void
    {
        try {
            $makeRequest();
        } catch (Error $error) {
            $this->assertInstanceOf(MultipleRecordsFoundException::class, $error->getPrevious());
            $this->assertEquals(new MultipleRecordsFoundException($count), $error->getPrevious());

            return;
        }

        $this->fail('Request did not throw an exception.');
    }
}
