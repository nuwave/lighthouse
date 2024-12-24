<?php declare(strict_types=1);

namespace Tests\Integration\Bind;

use GraphQL\Error\Error;
use Nuwave\Lighthouse\Bind\BindDefinition;
use Nuwave\Lighthouse\Bind\BindException;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Testing\MocksResolvers;
use Nuwave\Lighthouse\Testing\UsesTestSchema;
use Tests\DBTestCase;
use Tests\Utils\Models\User;
use Tests\Utils\Resolvers\SpyResolver;

use function factory;

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

        $makeRequest = fn () => $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            query {
                user(user: "1") {
                    id
                }
            }
            GRAPHQL);

        $this->assertThrows(
            $makeRequest,
            DefinitionException::class,
            'argument `user` of field `user` must be an existing class',
        );
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

        $makeRequest = fn () => $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
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

        $this->assertThrows(
            $makeRequest,
            DefinitionException::class,
            'field `users` of input `RemoveUsersInput` must be an existing class',
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

        $makeRequest = fn () => $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            query {
                user(user: "1") {
                    id
                }
            }
            GRAPHQL);

        $this->assertThrows(
            $makeRequest,
            DefinitionException::class,
            'argument `user` of field `user` must be an Eloquent model or a callable class',
        );
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

        $makeRequest = fn () => $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            mutation ($input: RemoveUsersInput!) {
                removeUsers(input: $input)
            }
            GRAPHQL,
            [
                'input' => [
                    'users' => ['1'],
                ]
            ],
        );

        $this->assertThrows(
            $makeRequest,
            DefinitionException::class,
            'field `users` of input `RemoveUsersInput` must be an Eloquent model or a callable class',
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
        $this->rethrowGraphQLErrors();
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
            }

            type Query {
                user(user: ID! @bind(class: "Tests\\Utils\\Models\\User")): User! @mock
            }
            GRAPHQL;

        $makeRequest = fn () => $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            query {
                user(user: "1") {
                    id
                }
            }
            GRAPHQL);

        $this->assertThrows(
            $makeRequest,
            Error::class,
            BindException::notFound('1', new BindDefinition('user', User::class, 'id', [], false))->getMessage(),
        );
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
                    user: ID! @bind(class: "Tests\\Utils\\Models\\User", optional: true)
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
            $users->each(function (User $user, int $key) use ($args): void {
                $this->assertTrue($user->is($args['users'][$key]));
            });
        });
    }

    public function testMissingModelCollectionBindingOnFieldArgument(): void
    {
        $this->rethrowGraphQLErrors();
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

        $makeRequest = fn () => $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            mutation ($users: [ID!]!) {
                removeUsers(users: $users)
            }
            GRAPHQL,
            [
                'users' => [$user->getKey(), '10'],
            ],
        );

        $this->assertThrows(
            $makeRequest,
            Error::class,
            BindException::missingRecords(['10'], new BindDefinition('users', User::class, 'id', [], false))
                ->getMessage(),
        );
    }

    public function testMissingOptionalModelCollectionBindingOnFieldArgument(): void
    {
        $this->rethrowGraphQLErrors();
        $user = factory(User::class)->create();
        $resolver = new SpyResolver(return: true);
        $this->mockResolver($resolver);
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
            }

            type Mutation {
                removeUsers(
                    users: [ID!]! @bind(class: "Tests\\Utils\\Models\\User", optional: true)
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
            $this->assertTrue($user->is($args['users'][0]));
        });
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
        $this->rethrowGraphQLErrors();
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

        $makeRequest = fn () => $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
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

        $this->assertThrows(
            $makeRequest,
            Error::class,
            BindException::notFound('1', new BindDefinition('user', User::class, 'id', [], false))->getMessage(),
        );
    }

    public function testMissingOptionalModelBindingOnInputField(): void
    {
        $this->mockResolver(fn (mixed $root, array $args) => $args['input']['user']);
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
            }

            input UserInput {
                user: ID! @bind(class: "Tests\\Utils\\Models\\User", optional: true)
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
            $users->each(function (User $user, int $key) use ($args): void {
                $this->assertTrue($user->is($args['input']['users'][$key]));
            });
        });
    }

    public function testMissingModelCollectionBindingOnInputField(): void
    {
        $this->rethrowGraphQLErrors();
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

        $makeRequest = fn () => $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
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

        $this->assertThrows(
            $makeRequest,
            Error::class,
            BindException::missingRecords(['10'], new BindDefinition('users', User::class, 'id', [], false))
                ->getMessage(),
        );
    }

    public function testMissingOptionalModelCollectionBindingOnInputField(): void
    {
        $this->rethrowGraphQLErrors();
        $user = factory(User::class)->create();
        $resolver = new SpyResolver(return: true);
        $this->mockResolver($resolver);
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type User {
                id: ID!
            }

            input RemoveUsersInput {
                users: [ID!]! @bind(class: "Tests\\Utils\\Models\\User", optional: true)
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
            $this->assertTrue($user->is($args['input']['users'][0]));
        });
    }
}
