<?php

namespace Tests\Integration\Validation;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Tests\TestCase;
use Tests\Utils\Queries\Foo;
use Tests\Utils\Rules\FooBarRule;

class RulesDirectiveTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        // Ensure we test for the result the end user receives
        $app['config']->set('app.debug', false);
    }

    public function testRequired(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(
                required: String @rules(apply: ["required"])
            ): Int
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(required: "foo")
            }
            ')
            ->assertJson([
                'data' => [
                    'foo' => Foo::THE_ANSWER,
                ],
            ]);

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo
            }
            ')
            ->assertGraphQLValidationKeys(['required']);
    }

    public function testDifferentDates(): void
    {
        $this->markTestSkipped('Not working right now, not sure how it can be fixed.');

        // @phpstan-ignore-next-line unreachable - that is expected though
        $this->schema = /** @lang GraphQL */ '
        "A date string with format `Y-m-d`, e.g. `2011-05-23`."
        scalar Date @scalar(class: "Nuwave\\\\Lighthouse\\\\Schema\\\\Types\\\\Scalars\\\\Date")

        type Query {
            foo(
                bar: Date @rules(apply: ["different:baz"])
                baz: Date
            ): Int
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(bar: "2020-05-15", baz: "1999-12-01")
            }
            ')
            ->assertJson([
                'data' => [
                    'foo' => Foo::THE_ANSWER,
                ],
            ]);

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(bar: "2020-05-15", baz: "2020-05-15")
            }
            ')
            ->assertGraphQLValidationKeys(['bar']);
    }

    public function testDateBefore(): void
    {
        $this->schema = /** @lang GraphQL */ '
        "A date string with format `Y-m-d`, e.g. `2011-05-23`."
        scalar Date @scalar(class: "Nuwave\\\\Lighthouse\\\\Schema\\\\Types\\\\Scalars\\\\Date")

        type Query {
            foo(
                early: Date @rules(apply: ["before:late"])
                late: Date
            ): Int
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(early: "1999-12-01", late: "2020-05-15")
            }
            ')
            ->assertExactJson([
                'data' => [
                    'foo' => Foo::THE_ANSWER,
                ],
            ]);

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(early: "2020-05-15", late: "1999-05-15")
            }
            ')
            ->assertGraphQLValidationKeys(['early']);
    }

    public function testCustomMessage(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(
                bar: ID @rules(
                    apply: ["required"]
                    messages: {
                        required: "custom message"
                    }
                )
            ): String
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo
            }
            ')
            ->assertGraphQLValidationError('bar', 'custom message');
    }

    public function testCustomAttributes(): void
    {
        $this->schema = /** @lang GraphQL */ '
        input FooInput {
            name: String @rules(
                apply: ["required"]
                attribute: "name"
            )
            type: FooTypeInput
        }

        input FooTypeInput {
            name: String @rules(
                apply: ["required"]
                attribute: "name"
            )
            type: String @rules(apply: ["required"])
        }

        type Query {
            foo(
                bar: ID @rules(
                    apply: ["required"]
                    attribute: "baz"
                )
                emails: [String] @rulesForArray(
                    apply: ["min:3"]
                    attribute: "email list"
                )
                input: FooInput
            ): String
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo(emails: [], input: {type: {}})
            }
            ')
            ->assertGraphQLValidationError('bar', 'The baz field is required.')
            ->assertGraphQLValidationError('emails', 'The email list must have at least 3 items.')
            ->assertGraphQLValidationError('input.name', 'The name field is required.')
            ->assertGraphQLValidationError('input.type.name', 'The name field is required.')
            ->assertGraphQLValidationError('input.type.type', 'The input.type.type field is required.');
    }

    public function testUsesCustomRuleClass(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            withCustomRuleClass(
                rules: String @rules(apply: ["Tests\\\\Utils\\\\Rules\\\\FooBarRule"])
                rulesForArray: [String!]! @rulesForArray(apply: ["Tests\\\\Utils\\\\Rules\\\\FooBarRule"])
            ): ID @mock
        }
        ';

        $this->mockResolverExpects(
            $this->never()
        );

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                withCustomRuleClass(
                    rules: "baz"
                    rulesForArray: []
                )
            }
            '
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
