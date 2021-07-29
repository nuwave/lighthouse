<?php

namespace Tests\Integration\Validation;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Validation\Validator;
use Nuwave\Lighthouse\Exceptions\ValidationException;
use Nuwave\Lighthouse\Support\AppVersion;
use Tests\TestCase;
use Tests\Utils\Validators\FooClosureValidator;

/**
 * Covers fundamentals of the validation process.
 */
class ValidationTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $app->make(ConfigRepository::class);

        // Ensure we test for the result the end user receives
        $config->set('app.debug', false);
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
                            'category' => ValidationException::CATEGORY,
                            ValidationException::CATEGORY => [
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
                            ValidationException::CATEGORY => [
                                'required' => [
                                    'The required field is required.',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
    }

    public function testCombinedRulesChangeTheirSemantics(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(
                bar: Int @rules(apply: ["min:42"])
                baz: Int @rules(apply: ["int", "min:42"])
            ): ID
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(
                    bar: 21
                    baz: 21
                )
            }
            ')
            ->assertGraphQLValidationError('bar', 'The bar must be at least 42 characters.')
            ->assertGraphQLValidationError('baz', 'The baz must be at least 42.');
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

        $message = AppVersion::atLeast(8.32)
            ? 'The bar must not be greater than 3 characters.'
            : 'The bar may not be greater than 3 characters.';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(bar: "fasdf")
            }
            ')
            ->assertGraphQLValidationError('bar', $message);
    }

    public function testSingleFieldReferencesAreQualified(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(input: Custom): String
        }

        input Custom {
            foo: String
            bar: String @rules(apply: ["required_if:foo,baz"])
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(
                    input: {
                        foo: "whatever"
                    }
                )
            }
            ')
            ->assertGraphQLValidationPasses();

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(
                    input: {
                        foo: "baz"
                    }
                )
            }
            ')
            ->assertGraphQLValidationError('input.bar', 'The input.bar field is required when input.foo is baz.');
    }

    public function testOptionalFieldReferencesAreQualified(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(input: Custom): String
        }

        input Custom {
            foo: String @rules(apply: ["after:2018-01-01"])
            bar: String @rules(apply: ["after:foo"])
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(
                    input: {
                        foo: "2019-01-01"
                        bar: "2020-01-01"
                    }
                )
            }
            ')
            ->assertGraphQLValidationPasses();

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(
                    input: {
                        foo: "2017-01-01"
                        bar: "2016-01-01"
                    }
                )
            }
            ')
            ->assertGraphQLValidationError('input.foo', 'The input.foo must be a date after 2018-01-01.')
            ->assertGraphQLValidationError('input.bar', 'The input.bar must be a date after input.foo.');
    }

    public function testCustomValidationWithReferencesAreQualified(): void
    {
        ValidatorFactory::extendDependent('equal_field', function ($attribute, $value, $parameters, Validator $validator) {
            $reference = Arr::get($validator->getData(), $parameters[0]);

            return $reference === $value;
        }, 'The :attribute must be equal to :other.');

        ValidatorFactory::replacer('equal_field', function (string $message, string $attribute, string $rule, array $parameters) {
            return str_replace(':other', implode(', ', $parameters), $message);
        });

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(input: Custom): String
        }

        input Custom {
            foo: Int
            bar: Int @rules(apply: ["with_reference:equal_field,0,foo"])
            baz: Int @rules(apply: ["with_reference:equal_field,0_1,foo,bar"])
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(
                    input: {
                        foo: 5
                        bar: 5
                        baz: 5
                    }
                )
            }
            ')
            ->assertGraphQLValidationPasses();

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(
                    input: {
                        foo: 5
                        bar: 6
                        baz: 7
                    }
                )
            }
            ')
            ->assertGraphQLValidationError('input.bar', 'The input.bar must be equal to input.foo.')
            ->assertGraphQLValidationError('input.baz', 'The input.baz must be equal to input.foo, input.bar.');
    }

    public function testCustomValidationClassWithReferencesAreQualified(): void
    {
        config(['app.debug' => true]);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(input: Custom): String
        }

        input Custom @validator(class: "Tests\\\\Utils\\\\Validators\\\\EqualFieldCustomRuleValidator") {
            foo: Int
            bar: Int
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(
                    input: {
                        foo: 5
                        bar: 6
                    }
                )
            }
            ')
            ->assertGraphQLValidationError('input.bar', 'input');
    }

    public function testMultipleFieldReferencesAreQualified(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(input: Custom): String
        }

        input Custom {
            foo: String
            bar: String @rules(apply: ["required_without_all:foo,baz"])
            baz: String
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(
                    input: {
                        foo: "whatever"
                    }
                )
            }
            ')
            ->assertGraphQLValidationPasses();

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(
                    input: {}
                )
            }
            ')
            ->assertGraphQLValidationError('input.bar', 'The input.bar field is required when none of input.foo / input.baz are present.');
    }

    public function testClosureRulesAreUsed(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(input: Custom): String
        }

        input Custom @validator(class: "Tests\\\\Utils\\\\Validators\\\\FooClosureValidator") {
            foo: String!
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(
                    input: {
                        foo: "foo"
                    }
                )
            }
            ')
            ->assertGraphQLValidationPasses();

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(
                    input: {
                        foo: "bar"
                    }
                )
            }
            ')
            ->assertGraphQLValidationError('input.foo', FooClosureValidator::notFoo('input.foo'));
    }
}
