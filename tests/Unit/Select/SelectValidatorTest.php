<?php declare(strict_types=1);

namespace Tests\Unit\Select;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class SelectValidatorTest extends TestCase
{
    #[DataProvider('validSchemas')]
    public function testAcceptsSchema(string $schema): void
    {
        $this->buildSchema($schema);

        $this->expectNotToPerformAssertions();
    }

    /** @return iterable<string, array{schema: string}> */
    public static function validSchemas(): iterable
    {
        yield 'fields with select or relation directives' => ['schema' => /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            users: [User!]! @all
        }

        type User {
            name: String @select(columns: ["name"])
            tasks: [Task!]! @hasMany
            tasksCount: Int! @count(relation: "tasks")
        }

        type Task {
            name: String
        }
        GRAPHQL];

        yield 'canRoot with type columns' => ['schema' => /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            users: [User!]! @all
        }

        type User @select(columns: []) {
            email: String @select(columns: ["email"]) @canRoot(ability: "view")
        }
        GRAPHQL];

        yield 'canResolved on a type without select' => ['schema' => /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            users: [User!]! @all @canResolved(ability: "view")
        }

        type User {
            name: String
        }
        GRAPHQL];
        yield 'canResolved on a type with type columns' => ['schema' => /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            users: [User!]! @paginate @canResolved(ability: "view")
        }

        type User @select(columns: ["company_id"]) {
            name: String @select(columns: ["name"])
        }
        GRAPHQL];

        yield 'count without relation' => ['schema' => /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            users: [User!]! @all
        }

        type User {
            name: String @select(columns: ["name"])
            allUsersCount: Int! @count(model: "User")
        }
        GRAPHQL];
    }

    #[DataProvider('invalidSchemas')]
    public function testRejectsSchema(string $message, string $schema): void
    {
        $this->expectExceptionObject(new DefinitionException($message));

        $this->buildSchema($schema);
    }

    /** @return iterable<string, array{message: string, schema: string}> */
    public static function invalidSchemas(): iterable
    {
        yield 'field without select' => [
            'message' => 'Add @select or a relation directive to User.email, because type User uses @select.',
            'schema' => /** @lang GraphQL */ <<<'GRAPHQL'
            type Query {
                users: [User!]! @all
            }

            type User {
                name: String @select(columns: ["name"])
                email: String
            }
            GRAPHQL,
        ];

        yield 'with only loads the relation, the field still reads an attribute' => [
            'message' => 'Add @select or a relation directive to User.taskNames, because type User uses @select.',
            'schema' => /** @lang GraphQL */ <<<'GRAPHQL'
            type Query {
                users: [User!]! @all
            }

            type User @select(columns: []) {
                taskNames: String @with(relation: "tasks")
            }
            GRAPHQL,
        ];

        yield 'canRoot without type columns' => [
            'message' => 'Add @select to type User with the columns its policies need, because User.email checks a policy against it.',
            'schema' => /** @lang GraphQL */ <<<'GRAPHQL'
            type Query {
                users: [User!]! @all
            }

            type User {
                email: String @select(columns: ["email"]) @canRoot(ability: "view")
            }
            GRAPHQL,
        ];

        yield 'canResolved on a paginated type without type columns' => [
            'message' => 'Add @select to type User with the columns its policies need, because Query.users checks a policy against it.',
            'schema' => /** @lang GraphQL */ <<<'GRAPHQL'
            type Query {
                users: [User!]! @paginate(type: CONNECTION) @canResolved(ability: "view")
            }

            type User {
                name: String @select(columns: ["name"])
            }
            GRAPHQL,
        ];

        yield 'type-level select only, field without select' => [
            'message' => 'Add @select or a relation directive to User.email, because type User uses @select.',
            'schema' => /** @lang GraphQL */ <<<'GRAPHQL'
            type Query {
                users: [User!]! @all
            }

            type User @select(columns: ["name"]) {
                email: String
            }
            GRAPHQL,
        ];

        yield 'canResolved on a paginated relation without type columns' => [
            'message' => 'Add @select to type Task with the columns its policies need, because User.tasks checks a policy against it.',
            'schema' => /** @lang GraphQL */ <<<'GRAPHQL'
            type Query {
                users: [User!]! @all
            }

            type User {
                tasks: [Task!]! @hasMany(type: CONNECTION) @canResolved(ability: "view")
            }

            type Task {
                name: String @select(columns: ["name"])
            }
            GRAPHQL,
        ];

        yield 'can with root without type columns' => [
            'message' => 'Add @select to type User with the columns its policies need, because User.email checks a policy against it.',
            'schema' => /** @lang GraphQL */ <<<'GRAPHQL'
            type Query {
                users: [User!]! @all
            }

            type User {
                email: String @select(columns: ["email"]) @can(ability: "view", root: true)
            }
            GRAPHQL,
        ];

        yield 'can with resolved without type columns' => [
            'message' => 'Add @select to type User with the columns its policies need, because Query.users checks a policy against it.',
            'schema' => /** @lang GraphQL */ <<<'GRAPHQL'
            type Query {
                users: [User!]! @all @can(ability: "view", resolved: true)
            }

            type User {
                name: String @select(columns: ["name"])
            }
            GRAPHQL,
        ];

        yield 'aggregate with builder reads the parent' => [
            'message' => 'Add @select or a relation directive to User.tasksCount, because type User uses @select.',
            'schema' => /** @lang GraphQL */ <<<'GRAPHQL'
            type Query {
                users: [User!]! @all
            }

            type User {
                name: String @select(columns: ["name"])
                tasksCount: Int! @aggregate(builder: "Tests\\Utils\\Queries\\Foo@bar", function: COUNT, column: "id")
            }
            GRAPHQL,
        ];
    }
}
