<?php

namespace Tests\Integration\Validation;

use Nuwave\Lighthouse\Support\Contracts\GlobalId;
use Tests\TestCase;
use Tests\Utils\Validators\EmailCustomAttributeValidator;
use Tests\Utils\Validators\EmailCustomMessageValidator;

class ValidatorDirectiveTest extends TestCase
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

        /** @var \Nuwave\Lighthouse\Support\Contracts\GlobalId $encoder */
        $encoder = app(GlobalId::class);
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
}
