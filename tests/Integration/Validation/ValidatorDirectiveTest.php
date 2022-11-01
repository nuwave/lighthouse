<?php

namespace Tests\Integration\Validation;

use Nuwave\Lighthouse\Support\Contracts\GlobalId;
use Tests\TestCase;
use Tests\Utils\Validators\EmailCustomAttributeValidator;
use Tests\Utils\Validators\EmailCustomMessageValidator;

final class ValidatorDirectiveTest extends TestCase
{
    public function testUsesValidatorByNamingConvention(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(input: SelfValidating): ID
        }

        input SelfValidating @validator {
            rules: [String!]!
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(
                    input: {
                        rules: ["email"]
                    }
                )
            }
            ')
            ->assertGraphQLValidationError('input.rules', 'The input.rules must be a valid email address.');
    }

    public function testUsesValidatorTwiceNested(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(input: FooInput): ID
        }

        input FooInput @validator {
            email: String
            self: FooInput
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(
                    input: {
                        email: "invalid"
                        self: {
                            email: "also-invalid"
                        }
                    }
                )
            }
            ')
            ->assertGraphQLValidationError('input.email', 'The input.email must be a valid email address.')
            ->assertGraphQLValidationError('input.self.email', 'The input.self.email must be a valid email address.');
    }

    public function testUsesSpecifiedValidatorClassWithoutNamespace(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(input: Bar): ID
        }

        input Bar @validator(class: "SelfValidatingValidator") {
            rules: [String!]!
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(
                    input: {
                        rules: ["email"]
                    }
                )
            }
            ')
            ->assertGraphQLValidationError('input.rules', 'The input.rules must be a valid email address.');
    }

    public function testUsesSpecifiedValidatorClassWithFullNamespace(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(input: Bar): ID
        }

        input Bar @validator(class: "Tests\\\\Utils\\\\Validators\\\\SelfValidatingValidator") {
            rules: [String!]!
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(
                    input: {
                        rules: ["email"]
                    }
                )
            }
            ')
            ->assertGraphQLValidationError('input.rules', 'The input.rules must be a valid email address.');
    }

    public function testNestedInputsRulesReceiveParameters(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(input: RulesWithParameters): ID
        }

        input RuleWithParameter {
            bar: [String!]!
        }

        input RulesWithParameters @validator {
            foo: [RuleWithParameter]!
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(
                    input: {
                        foo: {
                            bar: ["only 1 item"]
                        }
                    }
                )
            }
            ')
            ->assertGraphQLValidationError('input.foo.0.bar', 'The input.foo.0.bar must contain 2 items.');
    }

    public function testCustomMessage(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(input: EmailCustomMessage): ID
        }

        input EmailCustomMessage @validator {
            email: String
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(
                    input: {
                        email: "not an email"
                    }
                )
            }
            ')
            ->assertGraphQLValidationError('input.email', EmailCustomMessageValidator::MESSAGE);
    }

    public function testCustomAttributes(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(input: EmailCustomAttribute): ID
        }

        input EmailCustomAttribute @validator {
            email: String
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(
                    input: {
                        email: "not an email"
                    }
                )
            }
            ')
            ->assertGraphQLValidationError('input.email', EmailCustomAttributeValidator::MESSAGE);
    }

    public function testWithGlobalId(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(input: WithGlobalId!): ID
        }

        input WithGlobalId @validator {
            id: ID! @globalId
        }
        ';

        $encoder = $this->app->make(GlobalId::class);
        assert($encoder instanceof GlobalId);

        $globalId = $encoder->encode('asdf', '123');

        $this
            ->graphQL(/** @lang GraphQL */ '
            query ($id: ID!) {
                foo(
                    input: {
                        id: $id
                    }
                )
            }
            ', [
                'id' => $globalId,
            ])
            ->assertGraphQLValidationPasses();
    }

    public function testFieldValidatorConvention(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(email: String): ID @validator
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(
                    email: "not an email"
                )
            }
            ')
            ->assertGraphQLValidationError('email', 'The email must be a valid email address.');
    }

    public function testFieldValidatorConventionOnExtendedType(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            bar: ID
        }

        extend type Query {
            foo(email: String): ID @validator
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(
                    email: "not an email"
                )
            }
            ')
            ->assertGraphQLValidationError('email', 'The email must be a valid email address.');
    }

    public function testExplicitValidatorOnField(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            bar(email: String): ID @validator(class: "Query\\\\FooValidator")
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                bar(
                    email: "not an email"
                )
            }
            ')
            ->assertGraphQLValidationError('email', 'The email must be a valid email address.');
    }

    public function testArgumentReferencesAreQualified(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(input: BarRequiredWithoutFoo): String
        }

        input BarRequiredWithoutFoo @validator {
            foo: String
            bar: String
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
            ->assertGraphQLValidationError('input.bar', 'The input.bar field is required when input.foo is not present.');
    }
}
