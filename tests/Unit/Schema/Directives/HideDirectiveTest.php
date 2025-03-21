<?php declare(strict_types=1);

namespace Tests\Unit\Schema\Directives;

use Illuminate\Container\Container;
use Tests\TestCase;

final class HideDirectiveTest extends TestCase
{
    public function testHiddenOnTestingEnv(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            shownField: String! @mock
            hiddenField: String! @mock @hide(env: ["testing"])
        }
        ';

        $introspectionQuery = /** @lang GraphQL */ '
        {
            __schema {
                queryType {
                    fields {
                        name
                    }
                }
            }
        }
        ';

        $this->graphQL($introspectionQuery)
            ->assertJsonPath('data.__schema.queryType.fields.*.name', ['shownField']);

        $query = /** @lang GraphQL */ '
        {
            hiddenField
        }
        ';

        $this->graphQL($query)->assertGraphQLErrorMessage('Cannot query field "hiddenField" on type "Query". Did you mean "shownField"?');
    }

    public function testHiddenArgs(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            field(hiddenArg: String @hide(env: ["testing"])): String! @mock
        }
        ';

        $introspectionQuery = /** @lang GraphQL */ '
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
        ';

        $this->graphQL($introspectionQuery)
            ->assertJsonCount(0, 'data.__schema.queryType.fields.0.args');
    }

    public function testHiddenType(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            field: String! @mock
        }
        type HiddenType @hide(env: ["testing"]) {
            field: String!
        }
        ';

        $introspectionQuery = /** @lang GraphQL */ '
        {
            __schema {
                types {
                    name
                }
            }
        }
        ';

        $types = $this->graphQL($introspectionQuery)
            ->json('data.__schema.types.*.name');

        $this->assertNotContains('HiddenType', $types);
    }

    public function testHiddenInputField(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            field: String! @mock
        }

        input Input {
            hiddenInputField: String! @hide(env: ["testing"])
        }
        ';

        $introspectionQuery = /** @lang GraphQL */ '
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
        ';

        $types = $this->graphQL($introspectionQuery)
            ->json('data.__schema.types');

        $input = array_filter($types, fn (array $type): bool => $type['name'] === 'Input');

        $this->assertCount(1, $input);
        $this->assertEmpty(current($input)['inputFields']);
    }

    public function testHiddenWhenManuallySettingEnv(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            hiddenField: String! @mock @hide(env: ["production"])
        }
        ';

        $introspectionQuery = /** @lang GraphQL */ '
        {
            __schema {
                queryType {
                    fields {
                        name
                    }
                }
            }
        }
        ';

        Container::getInstance()->instance('env', 'production');
        $this->graphQL($introspectionQuery)
            ->assertJsonCount(0, 'data.__schema.queryType.fields');
    }

    public function testShownOnAnotherEnv(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            hiddenField: String! @mock @hide(env: ["production"])
        }
        ';

        $introspectionQuery = /** @lang GraphQL */ '
        {
            __schema {
                queryType {
                    fields {
                        name
                    }
                }
            }
        }
        ';

        $this->graphQL($introspectionQuery)
            ->assertJsonCount(1, 'data.__schema.queryType.fields')
            ->assertJsonPath('data.__schema.queryType.fields.0.name', 'hiddenField');
    }
}
