<?php

namespace Tests\Unit\Schema\Directives;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
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

        $this->mockResolver(function (): array {
            return [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'full_name' => 'John Doe',
                'input_object' => true,
            ];
        });

        $this->mockResolver(function ($root, $args): array {
            return [
                'first_name' => 'John' . ($args['foo'] ?? ''),
                'last_name' => 'Doe',
                'full_name' => 'John Doe',
                'input_object' => true,
            ];
        }, 'with exclude');

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(bar: String @rules(apply: ["required"])): User @mock

            withExcludeIfRule(
                foo: String @rules(apply: ["exclude_if:bar,baz"])
                bar: String
            ): User @mock(key: "with exclude")

            withExcludeUnlessRule(
                foo: String @rules(apply: ["exclude_unless:bar,barbaz"])
                bar: String
            ): User @mock(key: "with exclude")
        }

        type Mutation {
            foo(bar: String @rules(apply: ["required"])): User @mock

            withCustomRuleClass(
                rules: String @rules(apply: ["Tests\\\\Utils\\\\Rules\\\\FooBarRule"])
                rulesForArray: [String!]! @rulesForArray(apply: ["Tests\\\\Utils\\\\Rules\\\\FooBarRule"])
            ): User @mock
        }

        type User {
            first_name: String
            last_name: String
            full_name(
                formatted: Boolean
                    @rules(
                        apply: ["required"]
                        messages: {
                            required: "foobar"
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
                    apply: ["email", "max:20"]
                    messages: {
                        email: "Not an email"
                    }
                )
            emails: [String]
                @rules(
                    apply: ["email", "max:20"]
                    messages: {
                        email: "Not an email"
                    }
                )
            self: UserInput
        }
        ';
    }

    public function testCanValidateQueryRootFieldArguments(): void
    {
        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
{
    foo {
        first_name
    }
}
GRAPHQL
            )
            ->assertJson([
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
                                'column' => 5,
                            ],
                        ],
                        'path' => ['foo'],
                    ],
                ],
                'data' => [
                    'foo' => null,
                ],
            ])
            ->assertJson(
                $this
                    ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
mutation {
    foo {
        first_name
    }
}
GRAPHQL
                    )
                    ->jsonGet()
            );
    }

    public function testCanReturnValidFieldsAndErrorMessagesForInvalidFields(): void
    {
        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
{
    foo(bar: "foo") {
        first_name
        last_name
        full_name
    }
}
GRAPHQL
            )
            ->assertJson([
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
            ])
            ->assertJson(
                $this
                    ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
{
    foo(bar: "foo") {
        first_name
        last_name
        full_name
    }
}
GRAPHQL
                    )
                    ->jsonGet()
            );
    }

    public function testCanValidateRootMutationFieldArgs(): void
    {
        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
mutation {
    foo {
        first_name
        last_name
        full_name
    }
}
GRAPHQL
            )
            ->assertJson([
                'data' => [
                    'foo' => null,
                ],
            ])
            ->assertJsonCount(1, 'errors')
            ->assertJson(
                $this
                    ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
{
    foo {
        first_name
        last_name
        full_name
    }
}
GRAPHQL
                    )
                    ->jsonGet()
            );
    }

    public function testCanValidateArrayType(): void
    {
        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
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
GRAPHQL
            )
            ->assertJson([
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

    public function testCanReturnCorrectValidationForInputObjects(): void
    {
        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
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
GRAPHQL
            )
            ->assertJson([
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

    public function testUsesCustomRuleClass(): void
    {
        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
mutation {
    withCustomRuleClass(
        rules: "baz"
        rulesForArray: []
    ) {
        first_name
    }
}
GRAPHQL
            )
            ->assertJsonFragment([
                'rules' => [
                    FooBarRule::MESSAGE,
                ],
                'rulesForArray' => [
                    FooBarRule::MESSAGE,
                ],
            ]);
    }

    public function testSupportExcludeIf(): void
    {
        $r = $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
{
    withExcludeIfRule (
        foo: "baz"
        bar: "baz"
    ) {
        first_name
    }
}
GRAPHQL
            )->assertJson([
                'data' => [
                    'withExcludeIfRule' => [
                        'first_name' => 'John'
                    ]
                ]
            ]);
    }

    public function testSupportExcludeUnless(): void
    {
        $r = $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
{
    withExcludeUnlessRule (
        foo: "baz"
        bar: "baz"
    ) {
        first_name
    }
}
GRAPHQL
            )->assertJson([
                'data' => [
                    'withExcludeUnlessRule' => [
                        'first_name' => 'John'
                    ]
                ]
            ]);
    }

    public function testRulesHaveToBeArray(): void
    {
        $this->expectException(DefinitionException::class);
        $this->buildSchema(/** @lang GraphQL */ '
        type Query {
            foo(bar: ID @rules(apply: 123)): ID
        }
        ');
    }
}
