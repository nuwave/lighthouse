<?php declare(strict_types=1);

namespace Tests\Unit\Schema\Directives;

use Illuminate\Container\Container;
use Tests\TestCase;

final class ShowDirectiveTest extends TestCase
{
    public function testHiddenOnTestingEnv(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            shownField: String! @mock
            hiddenField: String! @mock @show(env: ["production"])
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

    public function testShownOnAnotherEnv(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            hiddenField: String! @mock @show(env: ["production"])
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
            ->assertJsonCount(1, 'data.__schema.queryType.fields')
            ->assertJsonPath('data.__schema.queryType.fields.0.name', 'hiddenField');
    }
}
