<?php declare(strict_types=1);

namespace Tests\Integration\OrderBy;

use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use Tests\TestCase;

final class OrderByDirectiveTest extends TestCase
{
    public function testGeneratesInputWithFullyQualifiedName(): void
    {
        $schemaString = /** @lang GraphQL */ '
        type Query {
            foo(
                orderBy: _ @orderBy(columns: ["bar"])
            ): ID @mock
        }
        ';

        $schema = $this->buildSchema($schemaString);
        $input = $schema->getType(
            'Query' // Parent
            . 'Foo' // Field
            . 'OrderBy' // Arg
            . 'OrderByClause', // Suffix
        );
        $this->assertInstanceOf(InputObjectType::class, $input);
    }

    public function testGeneratesInputWithFullyQualifiedNameUsingRelations(): void
    {
        $schemaString = /** @lang GraphQL */ '
        type Query {
            foo(
                orderBy: _ @orderBy(
                    columns: ["bar"],
                    relations: [
                        { relation: "foo" },
                        { relation: "baz", columns: ["foz"] },
                    ]
                )
            ): ID @mock
        }
        ';

        $schema = $this->buildSchema($schemaString);

        $clause = $schema->getType(
            'Query' // Parent
            . 'Foo' // Field
            . 'OrderBy' // Arg
            . 'RelationOrderByClause', // Suffix
        );
        $this->assertInstanceOf(InputObjectType::class, $clause);

        $foo = $schema->getType(
            'Query' // Parent
            . 'Foo' // Field
            . 'OrderBy' // Arg
            . 'Foo', // Relation
        );
        $this->assertInstanceOf(InputObjectType::class, $foo);

        $baz = $schema->getType(
            'Query' // Parent
            . 'Foo' // Field
            . 'OrderBy' // Arg
            . 'Baz', // Relation
        );
        $this->assertInstanceOf(InputObjectType::class, $baz);

        $bazEnum = $schema->getType(
            'Query' // Parent
            . 'Foo' // Field
            . 'OrderBy' // Arg
            . 'Baz' // Relation
            . 'Column', // Suffix
        );
        $this->assertInstanceOf(EnumType::class, $bazEnum);
    }

    public function testValidatesOnlyColumnOrOneRelationIsUsed(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(
                orderBy: _ @orderBy(
                    columns: ["bar"],
                    relations: [
                        { relation: "foo" },
                        { relation: "baz", columns: ["foz"] },
                    ]
                )
            ): ID @mock
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(orderBy: [{
                    column: BAR
                    foo: { aggregate: COUNT }
                    order: ASC
                }])
            }
            ')
            ->assertGraphQLValidationError('orderBy.0.column', 'The order by.0.column field prohibits order by.0.foo / order by.0.baz from being present.')
            ->assertGraphQLValidationError('orderBy.0.foo', 'The order by.0.foo field prohibits order by.0.column / order by.0.baz from being present.');

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(orderBy: [{
                    foo: { aggregate: COUNT }
                    baz: { aggregate: COUNT }
                    order: ASC
                }])
            }
            ')
            ->assertGraphQLValidationError('orderBy.0.foo', 'The order by.0.foo field prohibits order by.0.column / order by.0.baz from being present.')
            ->assertGraphQLValidationError('orderBy.0.baz', 'The order by.0.baz field prohibits order by.0.column / order by.0.foo from being present.');
    }
}
