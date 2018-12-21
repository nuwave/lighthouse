<?php

namespace Tests\Unit\Schema\Directives\Args;

use Tests\TestCase;
use Illuminate\Support\Arr;

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
                    'message' => 'Validation failed for the field [foo].',
                    'extensions' => [
                        'category' => 'validation',
                        'validation' => [
                            'bar' => [
                                'The bar field is required.',
                            ],
                        ],
                    ],
                ],
            ],
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

        $this->assertSame('John', Arr::get($result, 'data.foo.first_name'));
        $this->assertSame('Doe', Arr::get($result, 'data.foo.last_name'));

        $this->assertNull(Arr::get($result, 'data.foo.full_name'));
        $this->assertCount(1, Arr::get($result, 'errors'));
        $this->assertSame('Validation failed for the field [foo.full_name].', Arr::get($result, 'errors.0.message'));
        $this->assertSame(['formatted' => ['foobar']], Arr::get($result, 'errors.0.extensions.validation'));

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

        $this->assertNull(Arr::get($result, 'data.foo'));
        $this->assertCount(1, Arr::get($result, 'errors'));

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

        $this->assertEquals('John', Arr::get($result, 'data.foo.first_name'));
        $this->assertEquals('Doe', Arr::get($result, 'data.foo.last_name'));
        $this->assertNull(Arr::get($result, 'data.foo.full_name'));
        $this->assertCount(1, Arr::get($result, 'errors'));

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
    public function itCanValidateArrayType()
    {
        $query = '
        {
            foo(bar: "got it") {
                input_object(
                    input: {
                        emails: ["not-email", "not-email_2"]
                        self: {
                            emails: ["nested-not-email", "nested-not-email_2"]
                            self: {
                                emails: ["finally@valid.email", "not-email", "finally@valid.email", "not-email"]
                                self: {
                                    emails: ["this-would-be-valid-but-is@too.long"]
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

        $this->assertSame('John', Arr::get($queryResult, 'data.foo.first_name'));
        $this->assertEquals([
            'input.emails.0' => [
                'Not an email',
            ],
            'input.emails.1' => [
                'Not an email',
            ],
            'input.self.emails.0' => [
                'Not an email',
            ],
            'input.self.emails.1' => [
                'Not an email',
            ],
            'input.self.self.emails.1' => [
                'Not an email',
            ],
            'input.self.self.emails.3' => [
                'Not an email',
            ],
            'input.self.self.self.emails.0' => [
                'The input.self.self.self.emails.0 may not be greater than 20 characters.',
            ],
        ], Arr::get($queryResult, 'errors.0.extensions.validation'));
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

        $this->assertSame('John', Arr::get($queryResult, 'data.foo.first_name'));
        $this->assertEquals([
            'input.email' => [
                'Not an email',
            ],
            'input.self.email' => [
                'Not an email',
            ],
            'input.self.self.self.email' => [
                'The input.self.self.self.email may not be greater than 20 characters.',
            ],
        ], Arr::get($queryResult, 'errors.0.extensions.validation'));
    }

    public function resolve(): array
    {
        return [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'full_name' => 'John Doe',
            'input_object' => true,
        ];
    }

    protected function schema(): string
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
            emails: [String]
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
