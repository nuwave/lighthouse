<?php

namespace Tests\Unit\Schema\Directives\Args;

use Tests\TestCase;

class RulesDirectiveTest extends TestCase
{
    /**
     * @test
     */
    public function itCanValidateQueryRootFieldArguments()
    {
        $query = '{
            me {
                first_name
            }
        }';

        $result = $this->executeAndFormat($this->schema(), $query, false, [], true);
        $this->assertCount(1, array_get($result, 'errors.0.validation'));
        $this->assertNull($result['data']['me']);
    }

    /**
     * @test
     */
    public function itCanReturnValidFieldsAndErrorMessagesForInvalidFields()
    {
        $query = '{
            me(required: "foo") {
                first_name
                last_name
                full_name
            }
        }';

        $result = $this->executeAndFormat($this->schema(), $query, false, [], true);
        $this->assertEquals('John', array_get($result, 'data.me.first_name'));
        $this->assertEquals('Doe', array_get($result, 'data.me.last_name'));
        $this->assertNull(array_get($result, 'data.me.full_name'));
        $this->assertCount(1, array_get($result, 'errors.0.validation'));
    }

    /**
     * @test
     */
    public function itCanValidateRootMutationFieldArgs()
    {
        $mutation = '
        mutation {
            foo {
                first_name
                last_name
                full_name
            }
        }';

        $result = $this->executeAndFormat($this->schema(), $mutation, false, [], true);
        $this->assertNull(array_get($result, 'data.foo'));
        $this->assertCount(1, array_get($result, 'errors.0.validation'));
    }

    /**
     * @test
     */
    public function itCanProcessMutationsWithInvalidRetunObjectFields()
    {
        $mutation = '
        mutation {
            foo(bar: "foo") {
                first_name
                last_name
                full_name
            }
        }';

        $result = $this->executeAndFormat($this->schema(), $mutation, false, [], true);
        $this->assertEquals('John', array_get($result, 'data.foo.first_name'));
        $this->assertEquals('Doe', array_get($result, 'data.foo.last_name'));
        $this->assertNull(array_get($result, 'data.foo.full_name'));
        $this->assertCount(1, array_get($result, 'errors.0.validation'));
    }

    public function resolve()
    {
        return [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'full_name' => 'John Doe',
        ];
    }

    protected function schema()
    {
        $resolver = addslashes(self::class).'@resolve';

        return "
        type User {
            first_name: String
            last_name: String
            full_name(formatted: Boolean @rules(apply: [\"required\"])): String
        }
        type Mutation {
            foo(bar: String @rules(apply: [\"required\"])): User
                @field(resolver: \"{$resolver}\")
        }
        type Query {
            me(required: String @rules(apply: [\"required\"])): User
                @field(resolver: \"{$resolver}\")
        }";
    }
}
