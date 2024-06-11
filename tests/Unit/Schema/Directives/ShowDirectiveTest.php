<?php declare(strict_types=1);

namespace Tests\Unit\Schema\Directives;

use Illuminate\Container\Container;
use Tests\TestCase;

final class ShowDirectiveTest extends TestCase
{
    public function testHiddenOnTestingEnv(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            shownField: String! @mock
            hiddenField: String! @mock @show(env: ["production"])
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

    public function testShownOnAnotherEnv(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            hiddenField: String! @mock @show(env: ["production"])
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
            ->assertJsonCount(1, 'data.__schema.queryType.fields')
            ->assertJsonPath('data.__schema.queryType.fields.0.name', 'hiddenField');
    }
}
