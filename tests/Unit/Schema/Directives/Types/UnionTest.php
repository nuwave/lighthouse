<?php

namespace Tests\Unit\Schema\Directives\Types;

use Tests\TestCase;

class UnionTest extends TestCase
{
    const UNION_SCHEMA = '
        type User {
            id: ID!
        }
        type Employee {
            employeeId: ID!
        }
        union Person @union(resolver: "Tests\\\Unit\\\Schema\\\Directives\\\Types\\\UnionTest@person") = User | Employee
        type Query {
            person(type: String!): Person
                @field(resolver: "Tests\\\Unit\\\Schema\\\Directives\\\Types\\\UnionTest@resolve")
        }
    ';

    /**
     * @test
     */
    public function itResolvesUnionUser()
    {
        $result = $this->execute(self::UNION_SCHEMA, '
        {
            person(type: "user") {
                ...on User {
                    id
                }
            }
        }
        ');
        $this->assertEquals('user.id', array_get($result->data, 'person.id'));
    }

    /**
     * @test
     */
    public function itResolvesUnionEmployee()
    {
        $result = $this->execute(self::UNION_SCHEMA, '
        {
            person(type: "employee") {
                ...on Employee {
                    employeeId
                }
            }
        }
        ');
        $this->assertEquals('employee.id', array_get($result->data, 'person.employeeId'));
    }

    public function resolve($root, array $args)
    {
        return 'user' == $args['type']
            ? ['id' => 'user.id']
            : ['employeeId' => 'employee.id'];
    }

    public function person($value)
    {
        $type = isset($value['id']) ? 'User' : 'Employee';

        return schema()->instance($type);
    }
}
