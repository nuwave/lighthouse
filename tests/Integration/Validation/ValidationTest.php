<?php

namespace Tests\Integration\Validation;

use Tests\TestCase;

/**
 * Covers fundamentals of the validation process.
 */
class ValidationTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        // Ensure we test for the result the end user receives
        $app['config']->set('app.debug', false);
    }

    public function testRunsValidationBeforeCallingTheResolver(): void
    {
        $this->mockResolverExpects(
            $this->never()
        );

        $this->schema = /** @lang GraphQL */ '
        type Query {
            doNotCall(
                bar: String @rules(apply: ["required"])
            ): String @mock
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                doNotCall
            }
            ')
            ->assertGraphQLValidationKeys(['bar']);
    }

    public function testFullValidationError(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(
                bar: String @rules(apply: ["required"])
            ): Int
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo
            }
            ')
            ->assertExactJson([
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
                                'line' => 3,
                                'column' => 17,
                            ],
                        ],
                        'path' => ['foo'],
                    ],
                ],
                'data' => [
                    'foo' => null,
                ],
            ]);
    }

    public function testRunsOnNonRootFields(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Foo @mock
        }

        type Foo {
            bar: Int
            baz(
                required: Int @rules(apply: ["required"])
            ): Int
        }
        ';

        $this->mockResolver([
            'bar' => 123,
            'baz' => 'Will not be returned',
        ]);

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo {
                    bar
                    baz
                }
            }
            ')
            ->assertJson([
                'data' => [
                    'foo' => [
                        'bar' => 123,
                        'baz' => null,
                    ],
                ],
                'errors' => [
                    [
                        'path' => ['foo'],
                        'message' => 'Validation failed for the field [foo.baz].',
                        'extensions' => [
                            'validation' => [
                                'required' => [
                                    'The required field is required.',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
    }

    public function testValidatesDifferentPathsIndividually(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(
                bar: String @rules(apply: ["email"])
                input: [BazInput]
            ): ID @mock
        }

        input BazInput {
            baz: String @rules(apply: ["email"])
            input: BazInput
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(
                    bar: "invalid email"
                    input: [
                        {
                            baz: "invalid email"
                        }
                        {
                            input: {
                                baz: "invalid email"
                            }
                        }
                    ]
                )
            }
            ')
            ->assertGraphQLValidationKeys([
                'bar',
                'input.0.baz',
                'input.1.input.baz',
            ]);
    }

    public function testValidatesListContents(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(
                list: [String]
                    @rules(apply: ["required", "email"])
            ): ID
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(
                    list: [
                        null
                        "valid_email@example.com"
                        "invalid_email"
                    ]
                )
            }
            ')
            ->assertGraphQLValidationKeys([
                'list.0',
                'list.2',
            ]);
    }

    public function testSanitizeValidateTransform(): void
    {
        $this->mockResolver(function ($root, array $args): string {
            return $args['password'];
        });

        $this->schema = /** @lang GraphQL */ '
        type Query {
            password(
                password: String
                    @trim
                    @rules(apply: ["min:6", "max:20", "required_with:id"])
                    @hash
            ): String @mock
        }
        ';

        $validPasswordResult = $this->graphQL(/** @lang GraphQL */ '
        {
            password(password: " 1234567 ")
        }
        ');
        $password = $validPasswordResult->json('data.password');

        $this->assertNotSame(' 1234567 ', $password);
        $this->assertTrue(password_verify('1234567', $password));

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                password(password: " 1234 ")
            }
            ')
            ->assertJson([
                'data' => [
                    'password' => null,
                ],
            ])
            ->assertGraphQLValidationKeys(['password']);
    }

    public function testValidatesRulesOnInputObjectFields(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(
                input: FooInput
            ): Int
        }

        input FooInput {
            email: String @rules(apply: ["email"])
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(
                    input: {
                        email: "invalid email"
                    }
                )
            }
            ')
            ->assertGraphQLValidationKeys(['input.email']);

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(
                    input: {
                        email: "valid@email.com"
                    }
                )
            }
            ')
            ->assertGraphQLValidationPasses();
    }

    public function testCombinesArgumentValidationWhenGrouped(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(
                bar: String
                    @rules(apply: ["min:2"])
                    @rules(apply: ["max:3"])
            ): Int
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(bar: "f")
            }
            ')
            ->assertGraphQLValidationError('bar', 'The bar must be at least 2 characters.');

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(bar: "fasdf")
            }
            ')
            ->assertGraphQLValidationError('bar', 'The bar may not be greater than 3 characters.');
    }
}
