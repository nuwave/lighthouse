<?php declare(strict_types=1);

namespace Tests\Integration\Validation;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Support\AppVersion;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Utils\Queries\Foo;
use Tests\Utils\Rules\FooBarRule;

final class RulesDirectiveTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $config = $app->make(ConfigRepository::class);
        // Ensure we test for the result the end user receives
        $config->set('app.debug', false);
    }

    public function testRequired(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                type Query {
                    foo(
                        required: String @rules(apply: ["required"])
                    ): Int
                }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
                        {
                            foo(required: "foo")
                        }
            GRAPHQL)
            ->assertJson([
                'data' => [
                    'foo' => Foo::THE_ANSWER,
                ],
            ]);

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
                        {
                            foo
                        }
            GRAPHQL)
            ->assertGraphQLValidationKeys(['required']);
    }

    /** @return never */
    public function testDifferentDates(): void
    {
        $this->markTestSkipped('Not working right now, not sure how it can be fixed.');

        // @phpstan-ignore-next-line https://github.com/phpstan/phpstan-phpunit/issues/52
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                "A date string with format `Y-m-d`, e.g. `2011-05-23`."
                scalar Date @scalar(class: "Nuwave\\Lighthouse\\Schema\\Types\\Scalars\\Date")
        
                type Query {
                    foo(
                        bar: Date @rules(apply: ["different:baz"])
                        baz: Date
                    ): Int
                }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
                        {
                            foo(bar: "2020-05-15", baz: "1999-12-01")
                        }
            GRAPHQL)
            ->assertJson([
                'data' => [
                    'foo' => Foo::THE_ANSWER,
                ],
            ]);

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
                        {
                            foo(bar: "2020-05-15", baz: "2020-05-15")
                        }
            GRAPHQL)
            ->assertGraphQLValidationKeys(['bar']);
    }

    public function testDateBefore(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                "A date string with format `Y-m-d`, e.g. `2011-05-23`."
                scalar Date @scalar(class: "Nuwave\\Lighthouse\\Schema\\Types\\Scalars\\Date")
        
                type Query {
                    foo(
                        early: Date @rules(apply: ["before:late"])
                        late: Date
                    ): Int
                }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
                        {
                            foo(early: "1999-12-01", late: "2020-05-15")
                        }
            GRAPHQL)
            ->assertExactJson([
                'data' => [
                    'foo' => Foo::THE_ANSWER,
                ],
            ]);

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
                        {
                            foo(early: "2020-05-15", late: "1999-05-15")
                        }
            GRAPHQL)
            ->assertGraphQLValidationKeys(['early']);
    }

    public function testCustomMessages(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
                        {
                            foo
                        }
            GRAPHQL)
            ->assertGraphQLValidationError('bar', 'custom message');
    }

    public function testCustomMessagesWithMap(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
                        {
                            foo
                        }
            GRAPHQL)
            ->assertGraphQLValidationError('bar', 'custom message');
    }

    public function testCustomAttributes(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
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
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
                        {
                            foo(emails: [], input: { type: {} })
                        }
            GRAPHQL)
            ->assertGraphQLValidationError('bar', 'The baz field is required.')
            ->assertGraphQLValidationError('emails', AppVersion::atLeast(10.0)
                ? 'The email list field must have at least 3 items.'
                : 'The email list must have at least 3 items.')
            ->assertGraphQLValidationError('input.name', 'The name field is required.')
            ->assertGraphQLValidationError('input.type.name', 'The name field is required.')
            ->assertGraphQLValidationError('input.type.type', 'The input.type.type field is required.');
    }

    public function testUsesCustomRuleClass(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                type Query {
                    withCustomRuleClass(
                        rules: String @rules(apply: ["Tests\\Utils\\Rules\\FooBarRule"])
                        rulesForArray: [String!]! @rulesForArray(apply: ["Tests\\Utils\\Rules\\FooBarRule"])
                    ): ID @mock
                }
        GRAPHQL;

        $this->mockResolverExpects(
            $this->never(),
        );

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
                        {
                            withCustomRuleClass(
                                rules: "baz"
                                rulesForArray: []
                            )
                        }
            GRAPHQL,
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

    /** @dataProvider invalidApplyArguments */
    #[DataProvider('invalidApplyArguments')]
    public function testValidateApplyArgument(string $applyArgument): void
    {
        $this->expectException(DefinitionException::class);
        $this->buildSchema(/** @lang GraphQL */ <<<GRAPHQL
                type Query {
                    foo(bar: ID @rules(apply: {$applyArgument})): ID
                }
        GRAPHQL);
    }

    /** @return iterable<array{string}> */
    public static function invalidApplyArguments(): iterable
    {
        yield [/** @lang GraphQL */ <<<'GRAPHQL'
        123
        GRAPHQL];
        yield [/** @lang GraphQL */ <<<'GRAPHQL'
        "123"
        GRAPHQL];
        yield [/** @lang GraphQL */ <<<'GRAPHQL'
        []
        GRAPHQL];
        yield [/** @lang GraphQL */ <<<'GRAPHQL'
        [123]
        GRAPHQL];
    }

    /** @dataProvider invalidMessageArguments */
    #[DataProvider('invalidMessageArguments')]
    public function testValidateMessageArgument(string $messageArgument): void
    {
        $this->expectException(DefinitionException::class);
        $this->buildSchema(/** @lang GraphQL */ <<<GRAPHQL
                type Query {
                    foo(bar: ID @rules(apply: ["email"], messages: {$messageArgument})): ID
                }
        GRAPHQL);
    }

    /** @return iterable<array{string}> */
    public static function invalidMessageArguments(): iterable
    {
        yield [/** @lang GraphQL */ <<<'GRAPHQL'
        "foo"
        GRAPHQL];
        yield [/** @lang GraphQL */ <<<'GRAPHQL'
        {foo: 3}
        GRAPHQL];
        yield [/** @lang GraphQL */ <<<'GRAPHQL'
        [1, 2]
        GRAPHQL];
        yield [/** @lang GraphQL */ <<<'GRAPHQL'
        [{foo: 3}]
        GRAPHQL];
        yield [/** @lang GraphQL */ <<<'GRAPHQL'
        [{rule: "email"}]
        GRAPHQL];
        yield [/** @lang GraphQL */ <<<'GRAPHQL'
        [{rule: "email", message: null}]
        GRAPHQL];
        yield [/** @lang GraphQL */ <<<'GRAPHQL'
        [{rule: 3, message: "asfd"}]
        GRAPHQL];
    }

    public function testValidateElementsOfListType(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
                type Query {
                    foo(
                        bar: [String]
                        @rules(
                            apply: ["required"]
                        )
                    ): String
                }
        GRAPHQL;

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
                        {
                            foo(
                                bar: ["", null, "bar"]
                            )
                        }
            GRAPHQL)
            ->assertGraphQLValidationKeys(['bar.0', 'bar.1']);

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
                        {
                            foo(
                                bar: []
                            )
                        }
            GRAPHQL)
            ->assertJson([
                'data' => [
                    'foo' => Foo::THE_ANSWER,
                ],
            ]);

        $this
            ->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
                        {
                            foo
                        }
            GRAPHQL)
            ->assertJson([
                'data' => [
                    'foo' => Foo::THE_ANSWER,
                ],
            ]);
    }
}
