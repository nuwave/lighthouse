<?php

namespace Tests\Integration\Validation;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Tests\TestCase;
use Tests\Utils\Queries\Foo;
use Tests\Utils\Rules\FooBarRule;

class RulesDirectiveTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
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
