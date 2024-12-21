<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Testing\MocksResolvers;
use Nuwave\Lighthouse\Testing\UsesTestSchema;
use Tests\DBTestCase;
use Throwable;

final class BindingDirectiveTest extends DBTestCase
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

        $this->assertThrows($makeRequest, fn (DefinitionException $exception): bool => (
            $this->assertExceptionMessageContains(
                ['@bind', 'class', 'argument `user`', 'field `user`', 'NotAClass'],
                $exception,
            )
        ));
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

        $this->assertThrows($makeRequest, fn (DefinitionException $exception): bool => (
            $this->assertExceptionMessageContains(
                ['@bind', 'class', 'field `users`', 'input `RemoveUsersInput`', 'NotAClass'],
                $exception,
            )
        ));
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

        $this->assertThrows($makeRequest, fn (DefinitionException $exception): bool => (
            $this->assertExceptionMessageContains(
                ['@bind', 'class', 'argument `user`', 'field `user`', 'stdClass'],
                $exception,
            )
        ));
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

        $this->assertThrows($makeRequest, fn (DefinitionException $exception): bool => (
            $this->assertExceptionMessageContains(
                ['@bind', 'class', 'field `users`', 'input `RemoveUsersInput`', 'stdClass'],
                $exception,
            )
        ));
    }

    private function assertExceptionMessageContains(array $fragments, Throwable $exception): bool
    {
        return Collection::make($fragments)
            ->each(function (string $fragment) use ($exception): void {
                $this->assertStringContainsString($fragment, $exception->getMessage());
            })
            ->isNotEmpty();
    }
}
