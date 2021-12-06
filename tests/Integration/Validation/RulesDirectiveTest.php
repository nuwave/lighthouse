<?php

namespace Tests\Integration\Validation;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Tests\TestCase;
use Tests\Utils\Queries\Foo;
use Tests\Utils\Rules\FooBarRule;

class RulesDirectiveTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $app->make(ConfigRepository::class);

        // Ensure we test for the result the end user receives
        $config->set('app.debug', false);
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

        // @phpstan-ignore-next-line https://github.com/phpstan/phpstan-phpunit/issues/52
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

    public function testCustomMessages(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(
                bar: ID @rules(
                    apply: ["required"]
                    messages: [
                        {
                            rule: "required",
                            message: "custom message"
                        }
                    ]
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

    public function testCustomMessagesWithMap(): void
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

    /**
     * @dataProvider invalidApplyArguments
     */
    public function testValidateApplyArgument(string $applyArgument): void
    {
        $this->expectException(DefinitionException::class);
        $this->buildSchema(/** @lang GraphQL */ '
        type Query {
            foo(bar: ID @rules(apply: '.$applyArgument.')): ID
        }
        ');
    }

    /**
     * @return array<array<int, string>>
     */
    public function invalidApplyArguments(): array
    {
        return [
            [/** @lang GraphQL */ '123'],
            [/** @lang GraphQL */ '"123"'],
            [/** @lang GraphQL */ '[]'],
            [/** @lang GraphQL */ '[123]'],
        ];
    }

    /**
     * @dataProvider invalidMessageArguments
     */
    public function testValidateMessageArgument(string $messageArgument): void
    {
        $this->expectException(DefinitionException::class);
        $this->buildSchema(/** @lang GraphQL */ "
        type Query {
            foo(bar: ID @rules(apply: [\"email\"], messages: {$messageArgument})): ID
        }
        ");
    }

    /**
     * @return array<array<int, string>>
     */
    public function invalidMessageArguments(): array
    {
        return [
            [/** @lang GraphQL */ '"foo"'],
            [/** @lang GraphQL */ '{foo: 3}'],
            [/** @lang GraphQL */ '[1, 2]'],
            [/** @lang GraphQL */ '[{foo: 3}]'],
            [/** @lang GraphQL */ '[{rule: "email"}]'],
            [/** @lang GraphQL */ '[{rule: "email", message: null}]'],
            [/** @lang GraphQL */ '[{rule: 3, message: "asfd"}]'],
        ];
    }
}
