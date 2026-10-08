<?php declare(strict_types=1);

namespace Tests\Unit\Schema\Directives;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Tests\TestCase;

final class NestDirectiveTest extends TestCase
{
    public function testThrowsOnScalarType(): void
    {
        $this->expectExceptionObject(new DefinitionException('The @nest directive must be used on input object types, got String on Mutation.createUser:name.'));
        $this->buildSchema(/** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            dummy: Int
        }

        type Mutation {
            createUser(name: String @nest): User @create
        }

        type User {
            name: String
        }
        GRAPHQL);
    }

    public function testThrowsOnListType(): void
    {
        $this->expectExceptionObject(new DefinitionException('The @nest directive must be used on input object types, got [TaskInput!] on Mutation.createUser:tasks.'));
        $this->buildSchema(/** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            dummy: Int
        }

        type Mutation {
            createUser(tasks: [TaskInput!] @nest): User @create
        }

        input TaskInput {
            name: String
        }

        type User {
            name: String
        }
        GRAPHQL);
    }

    public function testThrowsOnInputFieldWithScalarType(): void
    {
        $this->expectExceptionObject(new DefinitionException('The @nest directive must be used on input object types, got String on CreateUserInput.name.'));
        $this->buildSchema(/** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            dummy: Int
        }

        type Mutation {
            createUser(input: CreateUserInput! @spread): User @create
        }

        input CreateUserInput {
            name: String @nest
        }

        type User {
            name: String
        }
        GRAPHQL);
    }

    public function testAllowsInputObjectType(): void
    {
        $this->buildSchema(/** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            dummy: Int
        }

        type Mutation {
            createUser(tasks: TaskOps @nest): User @create
        }

        input TaskOps {
            name: String
        }

        type User {
            name: String
        }
        GRAPHQL);

        $this->expectNotToPerformAssertions();
    }

    public function testAllowsNonNullInputObjectType(): void
    {
        $this->buildSchema(/** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            dummy: Int
        }

        type Mutation {
            createUser(tasks: TaskOps! @nest): User @create
        }

        input TaskOps {
            name: String
        }

        type User {
            name: String
        }
        GRAPHQL);

        $this->expectNotToPerformAssertions();
    }
}
