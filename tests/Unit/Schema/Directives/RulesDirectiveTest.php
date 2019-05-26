<?php

namespace Tests\Unit\Schema\Directives;

use Tests\TestCase;
use Tests\Utils\Rules\FooBarRule;

class RulesDirectiveTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        // Ensure we test for the result the end user receives
        $app['config']->set('app.debug', false);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->schema = "
        type Query {
            foo(bar: String @rules(apply: [\"required\"])): User
                @field(resolver: \"{$this->qualifyTestResolver()}\")
        }

        type Mutation {
            foo(bar: String @rules(apply: [\"required\"])): User 
                @field(resolver: \"{$this->qualifyTestResolver()}\")
            
            withCustomRuleClass(
                rules: String @rules(apply: [\"Tests\\\\Utils\\\\Rules\\\\FooBarRule\"])
                rulesForArray: [String!]! @rulesForArray(apply: [\"Tests\\\\Utils\\\\Rules\\\\FooBarRule\"]) 
            ): User
                @field(resolver: \"{$this->qualifyTestResolver()}\")
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

    /**
     * @test
     */
    public function itCanValidateQueryRootFieldArguments(): void
    {
        $this->graphQL('
        {
            foo {
                first_name
            }
        }
        ')->assertJson([
            'errors' => [
                [
                    'message' => 'Validation failed for the field [foo].',
                    'extensions' => [
                        'category' => 'validation',
                        'validation' => [
                            'bar' => [
                                'The bar field is required.',
                            ],
                        ],
                    ],
                    'locations' => [
                        [
                            'line' => 2,
                            'column' => 13,
                        ],
                    ],
                    'path' => ['foo'],
                ],
            ],
            'data' => [
                'foo' => null,
            ],
        ])->assertJson(
            $this->graphQL('
        mutation {
            foo {
                first_name
            }
        }
            ')->jsonGet()
        );
    }

    /**
     * @test
     */
    public function itCanReturnValidFieldsAndErrorMessagesForInvalidFields(): void
    {
        $this->graphQL('
        {
            foo(bar: "foo") {
                first_name
                last_name
                full_name
            }
        }
        ')->assertJson([
            'data' => [
                'foo' => [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'full_name' => null,
                ],
            ],
            'errors' => [
                [
                    'path' => ['foo'],
                    'message' => 'Validation failed for the field [foo.full_name].',
                    'extensions' => [
                        'validation' => [
                            'formatted' => [
                                'foobar',
                            ],
                        ],
                    ],
                ],
            ],
        ])->assertJson(
            $this->graphQL('
        mutation {
            foo(bar: "foo") {
                first_name
                last_name
                full_name
            }
        }
            ')->jsonGet()
        );
    }

    /**
     * @test
     */
    public function itCanValidateRootMutationFieldArgs(): void
    {
        $this->graphQL('
        mutation {
            foo {
                first_name
                last_name
                full_name
            }
        }
        ')->assertJson([
            'data' => [
                'foo' => null,
            ],
        ])->assertJsonCount(1, 'errors')
        ->assertJson(
            $this->graphQL('
        {
            foo {
                first_name
                last_name
                full_name
            }
        }
            ')->jsonGet()
        );
    }

    /**
     * @test
     */
    public function itCanValidateArrayType(): void
    {
        $this->graphQL('
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
        ')->assertJson([
            'data' => [
                'foo' => [
                    'first_name' => 'John',
                    'input_object' => null,
                ],
            ],
            'errors' => [
                [
                    'extensions' => [
                        'validation' => [
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
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function itCanReturnCorrectValidationForInputObjects(): void
    {
        $this->graphQL('
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
        ')->assertJson([
            'data' => [
                'foo' => [
                    'first_name' => 'John',
                    'input_object' => null,
                ],
            ],
            'errors' => [
                [
                    'extensions' => [
                        'validation' => [
                            'input.email' => [
                                'Not an email',
                            ],
                            'input.self.email' => [
                                'Not an email',
                            ],
                            'input.self.self.self.email' => [
                                'The input.self.self.self.email may not be greater than 20 characters.',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function itUsesCustomRuleClass(): void
    {
        $this->graphQL('
        mutation {
            withCustomRuleClass(
                rules: "baz"
                rulesForArray: []
            ) {
                first_name
            }
        }
        ')->assertJsonFragment([
            'rules' => [
                FooBarRule::MESSAGE,
            ],
            'rulesForArray' => [
                FooBarRule::MESSAGE,
            ],
        ]);
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
}
