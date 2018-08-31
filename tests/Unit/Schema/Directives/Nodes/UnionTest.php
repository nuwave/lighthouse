<?php

namespace Tests\Unit\Schema\Directives\Nodes;

use Tests\TestCase;
use GraphQL\Type\Definition\Type;

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
        $result = $this->executeQuery($this->schema(), $query);

        $this->assertEquals('user.id', array_get($result->data, 'person.id'));
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
        $result = $this->executeQuery($this->schema(), $query);

        $this->assertEquals('employee.id', array_get($result->data, 'person.employeeId'));
    }

    public function resolve($root, array $args): array
    {
        return 'user' == $args['type']
            ? ['id' => 'user.id']
            : ['employeeId' => 'employee.id'];
    }

    public function person(array $value): Type
    {
        $type = isset($value['id']) ? 'User' : 'Employee';

        return graphql()->types()->get($type);
    }

    protected function schema(): string
    {
        return '
        type User {
            id: ID!
        }
        
        type Employee {
            employeeId: ID!
        }
        
        union Person @union(resolver: "Tests\\\Unit\\\Schema\\\Directives\\\Nodes\\\UnionTest@person") = User | Employee
        
        type Query {
            person(type: String!): Person
                @field(resolver: "Tests\\\Unit\\\Schema\\\Directives\\\Nodes\\\UnionTest@resolve")
        }
        ';
    }
}
