<?php

namespace Tests\Integration\OrderBy;

use GraphQL\Type\Definition\InputObjectType;
use Tests\TestCase;

class OrderByDirectiveTest extends TestCase
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
            .'Foo' // Field
            .'OrderBy' // Arg
            .'OrderByClause' // Suffix
        );
        $this->assertInstanceOf(InputObjectType::class, $input);
    }
}
