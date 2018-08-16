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
        $query = '
        {
            foo {
                first_name
            }
        }
        ';

        $result = $this->execute($this->schema(), $query);
        $this->assertCount(1, array_get($result, 'errors'));
        $this->assertNull($result['data']['foo']);

        $mutation = '
        mutation {
            foo {
                first_name
            }
        }
        ';
        $mutationResult = $this->execute($this->schema(), $mutation);
        $this->assertSame($result, $mutationResult);
    }

    /**
     * @test
     */
    public function itCanReturnValidFieldsAndErrorMessagesForInvalidFields()
    {
        $query = '
        {
            foo(bar: "foo") {
                first_name
                last_name
                full_name
            }
        }
        ';

        $result = $this->execute($this->schema(), $query);
        $this->assertEquals('John', array_get($result, 'data.foo.first_name'));
        $this->assertEquals('Doe', array_get($result, 'data.foo.last_name'));
        $this->assertNull(array_get($result, 'data.foo.full_name'));
        $this->assertCount(1, array_get($result, 'errors'));
        $this->assertEquals('foobar', array_get($result, 'errors.0.message'));

        $mutation = '
        mutation {
            foo(bar: "foo") {
                first_name
                last_name
                full_name
            }
        }
        ';

        $mutationResult = $this->execute($this->schema(), $mutation);
        $this->assertSame($result, $mutationResult);
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
        }
        ';

        $result = $this->execute($this->schema(), $mutation);
        $this->assertNull(array_get($result, 'data.foo'));
        $this->assertCount(1, array_get($result, 'errors'));

        $query = '
        {
            foo {
                first_name
                last_name
                full_name
            }
        }
        ';

        $queryResult = $this->execute($this->schema(), $query);
        $this->assertSame($result, $queryResult);
    }

    /**
     * @test
     */
    public function itCanProcessMutationsWithInvalidReturnObjectFields()
    {
        $mutation = '
        mutation {
            foo(bar: "foo") {
                first_name
                last_name
                full_name
            }
        }
        ';

        $result = $this->execute($this->schema(), $mutation);
        $this->assertEquals('John', array_get($result, 'data.foo.first_name'));
        $this->assertEquals('Doe', array_get($result, 'data.foo.last_name'));
        $this->assertNull(array_get($result, 'data.foo.full_name'));
        $this->assertCount(1, array_get($result, 'errors'));

        $query = '
        {
            foo(bar: "foo") {
                first_name
                last_name
                full_name
            }
        }
        ';

        $queryResult = $this->execute($this->schema(), $query);
        $this->assertSame($result, $queryResult);
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
            full_name(
                formatted: Boolean @rules(
                    apply: [\"required\"]
                    messages: {
                        required: \"foobar\"
                    }
                )
            ): String
        }
        
        type Mutation {
            foo(bar: String @rules(apply: [\"required\"])): User
                @field(resolver: \"{$resolver}\")
        }
        
        type Query {
            foo(bar: String @rules(apply: [\"required\"])): User
                @field(resolver: \"{$resolver}\")
        }
        ";
    }
}
