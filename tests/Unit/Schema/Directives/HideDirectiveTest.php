<?php declare(strict_types=1);

namespace Tests\Unit\Schema\Directives;

use Illuminate\Container\Container;
use Tests\TestCase;

final class HideDirectiveTest extends TestCase
{
    public function testHiddenOnTestingEnv(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            shownField: String! @mock
            hiddenField: String! @mock @hide(env: ["testing"])
        }
        GRAPHQL;

        $introspectionQuery = /** @lang GraphQL */ <<<'GRAPHQL'
        {
            __schema {
                queryType {
                    fields {
                        name
                    }
                }
            }
        }
        GRAPHQL;

        $this->graphQL($introspectionQuery)
            ->assertJsonPath('data.__schema.queryType.fields.*.name', ['shownField']);

        $query = /** @lang GraphQL */ <<<'GRAPHQL'
        {
            hiddenField
        }
        GRAPHQL;

        $this->graphQL($query)->assertGraphQLErrorMessage('Cannot query field "hiddenField" on type "Query". Did you mean "shownField"?');
    }

    public function testHiddenArgs(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            field(hiddenArg: String @hide(env: ["testing"])): String! @mock
        }
        GRAPHQL;

        $introspectionQuery = /** @lang GraphQL */ <<<'GRAPHQL'
        {
            __schema {
                queryType {
                    fields {
                        args {
                            name
                        }
                    }
                }
            }
        }
        GRAPHQL;

        $this->graphQL($introspectionQuery)
            ->assertJsonCount(0, 'data.__schema.queryType.fields.0.args');
    }

    public function testHiddenType(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            field: String! @mock
        }
        type HiddenType @hide(env: ["testing"]) {
            field: String!
        }
        GRAPHQL;

        $introspectionQuery = /** @lang GraphQL */ <<<'GRAPHQL'
        {
            __schema {
                types {
                    name
                }
            }
        }
        GRAPHQL;

        $types = $this->graphQL($introspectionQuery)
            ->json('data.__schema.types.*.name');

        $this->assertNotContains('HiddenType', $types);
    }

    public function testHiddenInputField(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            field: String! @mock
        }

        input Input {
            hiddenInputField: String! @hide(env: ["testing"])
        }
        GRAPHQL;

        $introspectionQuery = /** @lang GraphQL */ <<<'GRAPHQL'
        {
            __schema {
                types {
                    name
                    inputFields {
                        name
                    }
                }
            }
        }
        GRAPHQL;

        $types = $this->graphQL($introspectionQuery)
            ->json('data.__schema.types');

        $input = array_filter($types, fn (array $type): bool => $type['name'] === 'Input');

        $this->assertCount(1, $input);
        $this->assertEmpty(current($input)['inputFields']);
    }

    public function testHiddenWhenManuallySettingEnv(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            hiddenField: String! @mock @hide(env: ["production"])
        }
        GRAPHQL;

        $introspectionQuery = /** @lang GraphQL */ <<<'GRAPHQL'
        {
            __schema {
                queryType {
                    fields {
                        name
                    }
                }
            }
        }
        GRAPHQL;

        Container::getInstance()->instance('env', 'production');
        $this->graphQL($introspectionQuery)
            ->assertJsonCount(0, 'data.__schema.queryType.fields');
    }

    public function testShownOnAnotherEnv(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            hiddenField: String! @mock @hide(env: ["production"])
        }
        GRAPHQL;

        $introspectionQuery = /** @lang GraphQL */ <<<'GRAPHQL'
        {
            __schema {
                queryType {
                    fields {
                        name
                    }
                }
            }
        }
        GRAPHQL;

        $this->graphQL($introspectionQuery)
            ->assertJsonCount(1, 'data.__schema.queryType.fields')
            ->assertJsonPath('data.__schema.queryType.fields.0.name', 'hiddenField');
    }
}
