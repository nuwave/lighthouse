<?php declare(strict_types=1);

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
final class ValidationTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $config = $app->make(ConfigRepository::class);
        // Ensure we test for the result the end user receives
        $config->set('app.debug', false);
    }

    public function testRunsValidationBeforeCallingTheResolver(): void
    {
        $this->mockResolverExpects(
            $this->never(),
        );

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            doNotCall(
                bar: String @rules(apply: ["required"])
            ): String @mock
        }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                doNotCall
            }
            GRAPHQL)
            ->assertGraphQLValidationKeys(['bar']);
    }

    public function testFullValidationError(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo(
                bar: String @rules(apply: ["required"])
            ): Int
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo
        }
        GRAPHQL)->assertExactJson([
            'errors' => [
                [
                    'message' => 'Validation failed for the field [foo].',
                    'extensions' => [
                        ValidationException::KEY => [
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
        ]);
    }

    public function testFullValidationErrorWithoutLocationParse(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.parse_source_location', false);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo(
                bar: String @rules(apply: ["required"])
            ): Int
        }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                foo
            }
            GRAPHQL)
            ->assertExactJson([
                'errors' => [
                    [
                        'message' => 'Validation failed for the field [foo].',
                        'extensions' => [
                            ValidationException::KEY => [
                                'bar' => [
                                    'The bar field is required.',
                                ],
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
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo: Foo @mock
        }

        type Foo {
            bar: Int
            baz(
                required: Int @rules(apply: ["required"])
            ): Int
        }
        GRAPHQL;

        $this->mockResolver([
            'bar' => 123,
            'baz' => 'Will not be returned',
        ]);

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                foo {
                    bar
                    baz
                }
            }
            GRAPHQL)
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
                            ValidationException::KEY => [
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
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo(
                bar: Int @rules(apply: ["min:42"])
                baz: Int @rules(apply: ["int", "min:42"])
            ): ID
        }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                foo(
                    bar: 21
                    baz: 21
                )
            }
            GRAPHQL)
            ->assertGraphQLValidationError('bar', AppVersion::atLeast(10.0)
                ? 'The bar field must be at least 42 characters.'
                : 'The bar must be at least 42 characters.')
            ->assertGraphQLValidationError('baz', AppVersion::atLeast(10.0)
                ? 'The baz field must be at least 42.'
                : 'The baz must be at least 42.');
    }

    public function testValidatesDifferentPathsIndividually(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
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
            GRAPHQL)
            ->assertGraphQLValidationKeys([
                'bar',
                'input.0.baz',
                'input.1.input.baz',
            ]);
    }

    public function testValidatesListContents(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo(
                list: [String]
                    @rules(apply: ["required", "email"])
            ): ID
        }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                foo(
                    list: [
                        null
                        "valid_email@example.com"
                        "invalid_email"
                    ]
                )
            }
            GRAPHQL)
            ->assertGraphQLValidationKeys([
                'list.0',
                'list.2',
            ]);
    }

    public function testSanitizeValidateTransform(): void
    {
        $this->mockResolver(static fn ($_, array $args): string => $args['password']);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            password(
                password: String
                    @trim
                    @rules(apply: ["min:6", "max:20", "required_with:id"])
                    @hash
            ): String @mock
        }
        GRAPHQL;

        $validPasswordResult = $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            password(password: " 1234567 ")
        }
        GRAPHQL);
        $password = $validPasswordResult->json('data.password');

        $this->assertNotSame(' 1234567 ', $password);
        $this->assertTrue(password_verify('1234567', $password));

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                password(password: " 1234 ")
            }
            GRAPHQL)
            ->assertJson([
                'data' => [
                    'password' => null,
                ],
            ])
            ->assertGraphQLValidationKeys(['password']);
    }

    public function testValidatesRulesOnInputObjectFields(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo(
                input: FooInput
            ): Int
        }

        input FooInput {
            email: String @rules(apply: ["email"])
        }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                foo(
                    input: {
                        email: "invalid email"
                    }
                )
            }
            GRAPHQL)
            ->assertGraphQLValidationKeys(['input.email']);

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                foo(
                    input: {
                        email: "valid@email.com"
                    }
                )
            }
            GRAPHQL)
            ->assertGraphQLValidationPasses();
    }

    public function testCombinesArgumentValidationWhenGrouped(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo(
                bar: String
                    @rules(apply: ["min:2"])
                    @rules(apply: ["max:3"])
            ): Int
        }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                foo(bar: "f")
            }
            GRAPHQL)
            ->assertGraphQLValidationError('bar', AppVersion::atLeast(10.0)
                ? 'The bar field must be at least 2 characters.'
                : 'The bar must be at least 2 characters.');

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                foo(bar: "fasdf")
            }
            GRAPHQL)
            ->assertGraphQLValidationError('bar', AppVersion::atLeast(10.0)
                ? 'The bar field must not be greater than 3 characters.'
                : 'The bar must not be greater than 3 characters.');
    }

    public function testSingleFieldReferencesAreQualified(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo(input: Custom): String
        }

        input Custom {
            foo: String
            bar: String @rules(apply: ["required_if:foo,baz"])
        }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                foo(
                    input: {
                        foo: "whatever"
                    }
                )
            }
            GRAPHQL)
            ->assertGraphQLValidationPasses();

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                foo(
                    input: {
                        foo: "baz"
                    }
                )
            }
            GRAPHQL)
            ->assertGraphQLValidationError('input.bar', 'The input.bar field is required when input.foo is baz.');
    }

    public function testOptionalFieldReferencesAreQualified(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo(input: Custom): String
        }

        input Custom {
            foo: String @rules(apply: ["after:2018-01-01"])
            bar: String @rules(apply: ["after:foo"])
        }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                foo(
                    input: {
                        foo: "2019-01-01"
                        bar: "2020-01-01"
                    }
                )
            }
            GRAPHQL)
            ->assertGraphQLValidationPasses();

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                foo(
                    input: {
                        foo: "2017-01-01"
                        bar: "2016-01-01"
                    }
                )
            }
            GRAPHQL)
            ->assertGraphQLValidationError('input.foo', AppVersion::atLeast(10.0)
                ? 'The input.foo field must be a date after 2018-01-01.'
                : 'The input.foo must be a date after 2018-01-01.')
            ->assertGraphQLValidationError('input.bar', AppVersion::atLeast(10.0)
                ? 'The input.bar field must be a date after input.foo.'
                : 'The input.bar must be a date after input.foo.');
    }

    public function testCustomValidationWithReferencesAreQualified(): void
    {
        ValidatorFactory::extendDependent('equal_field', static function ($attribute, $value, $parameters, Validator $validator): bool {
            $reference = Arr::get($validator->getData(), $parameters[0]);

            return $reference === $value;
        }, 'The :attribute must be equal to :other.');

        ValidatorFactory::replacer('equal_field', static fn (string $message, string $attribute, string $rule, array $parameters): string => str_replace(':other', implode(', ', $parameters), $message));

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo(input: Custom): String
        }

        input Custom {
            foo: Int
            bar: Int @rules(apply: ["with_reference:equal_field,0,foo"])
            baz: Int @rules(apply: ["with_reference:equal_field,0_1,foo,bar"])
        }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                foo(
                    input: {
                        foo: 5
                        bar: 5
                        baz: 5
                    }
                )
            }
            GRAPHQL)
            ->assertGraphQLValidationPasses();

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                foo(
                    input: {
                        foo: 5
                        bar: 6
                        baz: 7
                    }
                )
            }
            GRAPHQL)
            ->assertGraphQLValidationError('input.bar', 'The input.bar must be equal to input.foo.')
            ->assertGraphQLValidationError('input.baz', 'The input.baz must be equal to input.foo, input.bar.');
    }

    public function testCustomValidationClassWithReferencesAreQualified(): void
    {
        config(['app.debug' => true]);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo(input: Custom): String
        }

        input Custom @validator(class: "Tests\\Utils\\Validators\\EqualFieldCustomRuleValidator") {
            foo: Int
            bar: Int
        }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                foo(
                    input: {
                        foo: 5
                        bar: 6
                    }
                )
            }
            GRAPHQL)
            ->assertGraphQLValidationError('input.bar', 'input');
    }

    public function testMultipleFieldReferencesAreQualified(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo(input: Custom): String
        }

        input Custom {
            foo: String
            bar: String @rules(apply: ["required_without_all:foo,baz"])
            baz: String
        }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                foo(
                    input: {
                        foo: "whatever"
                    }
                )
            }
            GRAPHQL)
            ->assertGraphQLValidationPasses();

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                foo(
                    input: {}
                )
            }
            GRAPHQL)
            ->assertGraphQLValidationError('input.bar', 'The input.bar field is required when none of input.foo / input.baz are present.');
    }

    public function testClosureRulesAreUsed(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo(input: Custom): String
        }

        input Custom @validator(class: "Tests\\Utils\\Validators\\FooClosureValidator") {
            foo: String!
        }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                foo(
                    input: {
                        foo: "foo"
                    }
                )
            }
            GRAPHQL)
            ->assertGraphQLValidationPasses();

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                foo(
                    input: {
                        foo: "bar"
                    }
                )
            }
            GRAPHQL)
            ->assertGraphQLValidationError('input.foo', FooClosureValidator::notFoo('input.foo'));
    }

    public function testReturnsMultipleValidationErrorsPerField(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo(
                input: FooInput
            ): Int
        }

        input FooInput {
            email: String @rules(apply: ["email", "min:16"])
        }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
            {
                foo(
                    input: {
                        email: "invalid"
                    }
                )
            }
            GRAPHQL)
            ->assertGraphQLValidationError('input.email', AppVersion::atLeast(10.0)
                ? 'The input.email field must be a valid email address.'
                : 'The input.email must be a valid email address.')
            ->assertGraphQLValidationError('input.email', AppVersion::atLeast(10.0)
                ? 'The input.email field must be at least 16 characters.'
                : 'The input.email must be at least 16 characters.');
    }
}
