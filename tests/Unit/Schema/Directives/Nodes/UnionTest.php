<?php

namespace Tests\Unit\Schema\Directives\Nodes;

use Tests\TestCase;

class UnionTest extends TestCase
{
    /**
     * @test
     */
    public function itResolvesUnionUser()
    {
        $query = '
        {
            person(type: "user") {
                ...on User {
                    id
                }
            }
        }
        ';

        foreach ($this->schemas() as $schema) {
            $result = $this->executeQuery($schema, $query);

            $this->assertEquals('user.id', array_get($result->data, 'person.id'));
        }
    }

    /**
     * @test
     */
    public function itResolverUnionEmployee()
    {
        $query = '
        {
            person(type: "employee") {
                ...on Employee {
                    employeeId
                }
            }
        }
        ';

        foreach ($this->schemas() as $schema) {
            $result = $this->executeQuery($schema, $query);

            $this->assertEquals('employee.id', array_get($result->data, 'person.employeeId'));
        }
    }

    public function resolve($root, array $args): array
    {
        return 'user' == $args['type']
            ? ['id' => 'user.id']
            : ['employeeId' => 'employee.id'];
    }

    protected function schemas(): array
    {
        return [
            $this->schema(),
            $this->schemaWithOutUnionDirective()
        ];
    }

    /**
     * Giv the schema with out the `union` directive
     * if the union directive is not specified
     * fallback to the default resolver:
     * `namespace/UnionTypeName::resolve`
     *
     * @return string
     */
    protected function schemaWithOutUnionDirective(): string
    {
        return $this->schema(false);
    }

    protected function schema($withUnionDirective = true): string
    {
        $unionDirective = $withUnionDirective ? '@union(resolver: "Tests\\\Utils\\\Unions\\\Person@resolve")' : '';

        return <<< GRAPHQL
        type User {
            id: ID!
        }
        
        type Employee {
            employeeId: ID!
        }
        
        union Person $unionDirective = User | Employee
        
        type Query {
            person(type: String!): Person
                @field(resolver: "Tests\\\Unit\\\Schema\\\Directives\\\Nodes\\\UnionTest@resolve")
        }
GRAPHQL;
    }
}
