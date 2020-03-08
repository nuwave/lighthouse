<?php

namespace Tests\Integration\Validation;

use Tests\DBTestCase;
use Tests\Utils\Validators\EmailCustomMessageValidator;

class ValidatorTest extends DBTestCase
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

        $fails = $this->graphQL(/** @lang GraphQL */ '
        {
            foo(
                input: {
                    rules: ["email"]
                }
            )
        }
        ');

        $this->assertValidationError($fails, 'input.rules', 'The input.rules must be a valid email address.');
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

        $fails = $this->graphQL(/** @lang GraphQL */ '
        {
            foo(
                input: {
                    rules: ["email"]
                }
            )
        }
        ');

        $this->assertValidationError($fails, 'input.rules', 'The input.rules must be a valid email address.');
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

        $fails = $this->graphQL(/** @lang GraphQL */ '
        {
            foo(
                input: {
                    rules: ["email"]
                }
            )
        }
        ');

        $this->assertValidationError($fails, 'input.rules', 'The input.rules must be a valid email address.');
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

        $fails = $this->graphQL(/** @lang GraphQL */ '
        {
            foo(
                input: {
                    email: "not an email"
                }
            )
        }
        ');

        $this->assertValidationError($fails, 'input.email', EmailCustomMessageValidator::MESSAGE);
    }

    public function testFieldValidatorConvention(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(email: String): ID @validator
        }
        ';

        $fails = $this->graphQL(/** @lang GraphQL */ '
        {
            foo(
                email: "not an email"
            )
        }
        ');

        $this->assertValidationError($fails, 'email', 'The email must be a valid email address.');
    }

    public function testExplicitValidatorOnField(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            bar(email: String): ID @validator(class: "Query\\\\FooValidator")
        }
        ';

        $fails = $this->graphQL(/** @lang GraphQL */ '
        {
            bar(
                email: "not an email"
            )
        }
        ');

        $this->assertValidationError($fails, 'email', 'The email must be a valid email address.');
    }
}
