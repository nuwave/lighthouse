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

        $result = $this->executeWithoutDebug($this->schema(), $query);
        $this->assertEquals([
            'data' => ['foo' => null],
            'errors' => [
                [
                    'path' => ['foo'],
                    'locations' => [['line' => 3, 'column' => 13]],
                    'message' => 'Validation failed for the field [foo]',
                    'category' => 'validation',
                    'extensions' => [
                        'validation' => [
                            'bar' => [
                                'The bar field is required.'
                            ]
                        ]
                    ]
                ],
            ]
        ], $result);

        $mutation = '
        mutation {
            foo {
                first_name
            }
        }
        ';
        $mutationResult = $this->executeWithoutDebug($this->schema(), $mutation);
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

        $result = $this->executeWithoutDebug($this->schema(), $query);

        $this->assertSame('John', array_get($result, 'data.foo.first_name'));
        $this->assertSame('Doe', array_get($result, 'data.foo.last_name'));

        $this->assertNull(array_get($result, 'data.foo.full_name'));
        $this->assertCount(1, array_get($result, 'errors'));
        $this->assertSame('Validation failed for the field [foo.full_name]', array_get($result, 'errors.0.message'));
        $this->assertSame(['formatted' => ['foobar']], array_get($result, 'errors.0.extensions.validation'));

        $mutation = '
        mutation {
            foo(bar: "foo") {
                first_name
                last_name
                full_name
            }
        }
        ';

        $mutationResult = $this->executeWithoutDebug($this->schema(), $mutation);
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

        $result = $this->executeWithoutDebug($this->schema(), $mutation);
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

        $queryResult = $this->executeWithoutDebug($this->schema(), $query);
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

        $result = $this->executeWithoutDebug($this->schema(), $mutation);
        $this->assertSame('John', array_get($result, 'data.foo.first_name'));
        $this->assertSame('Doe', array_get($result, 'data.foo.last_name'));
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

        $queryResult = $this->executeWithoutDebug($this->schema(), $query);
        $this->assertSame($result, $queryResult);
    }

    /**
     * @test
     */
    public function itCanReturnCorrectValidationForInputObjects()
    {
        $query = '
        {
            foo(bar: "got it") {
                input_object(
                    input: {
                        email: "not-email"
                        self: {
                            email: "nested-not-email"
                            self: {
                                email: "finally@valid.email"
                                self: {
                                    email: "this-would-be-valid-but-is@too.long"
                                }
                            }
                        }
                    }
                )
                first_name
            }
        }
        ';

        $queryResult = $this->executeWithoutDebug($this->schema(), $query);

        $this->assertSame('John', array_get($queryResult, 'data.foo.first_name'));
        $this->assertEquals([
            'input.email' => ['Not an email'],
            'input.self.email' => ['Not an email'],
            'input.self.self.self.email' => ['The input.self.self.self.email may not be greater than 20 characters.'],
        ], array_get($queryResult, 'errors.0.extensions.validation'));
    }

    public function resolve()
    {
        return [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'full_name' => 'John Doe',
            'input_object' => true
        ];
    }

    protected function schema()
    {
        $resolver = addslashes(self::class).'@resolve';

        return "
        type Query {
            foo(bar: String @rules(apply: [\"required\"])): User
                @field(resolver: \"{$resolver}\")
        }
        
        type Mutation {
            foo(bar: String @rules(apply: [\"required\"])): User
                @field(resolver: \"{$resolver}\")
        }
        
        type User {
            first_name: String
            last_name: String
            full_name(
                formatted: Boolean
                    @rules(
                        apply: [\"required\"]
                        messages: {
                            required: \"foobar\"
                        }
                    )
            ): String
            input_object(
                input: UserInput
            ): Boolean
        }
        
        input UserInput {
            email: String
                @rules(
                    apply: [\"email\", \"max:20\"]
                    messages: {
                        email: \"Not an email\"
                    }
                )
            self: UserInput
        }           
        ";
    }
}
