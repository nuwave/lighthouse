<?php

namespace Tests\Unit\Schema\Directives;

use GraphQL\Validator\Rules\QueryComplexity;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Tests\TestCase;
use Tests\Utils\Queries\Foo;

class ComplexityDirectiveTest extends TestCase
{
    const CUSTOM_COMPLEXITY = 123;

    public function testDeniesQuery(): void
    {
        $max = 1;
        $this->setMaxQueryComplexity($max);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            posts: [Post!]! @all
        }

        type Post {
            title: String
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            posts {
                title
            }
        }
        ')->assertGraphQLErrorMessage(QueryComplexity::maxQueryComplexityErrorMessage($max, 2));
    }

    public function testKnowsPagination(): void
    {
        $max = 1;
        $this->setMaxQueryComplexity($max);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            posts: [Post!]!
                @complexity
                @paginate
        }

        type Post {
            title: String
        }
        ';

        // 1 for the field posts
        // + 2 for data & title * 10 first items
        $expectedCount = 21;

        $this->graphQL(/** @lang GraphQL */ '
        {
            posts(first: 10) {
                data {
                    title
                }
            }
        }
        ')->assertGraphQLErrorMessage(QueryComplexity::maxQueryComplexityErrorMessage($max, $expectedCount));
    }

    public function testCustomComplexityResolver(): void
    {
        $max = 1;
        $this->setMaxQueryComplexity($max);

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        type Query {
            foo: ID @complexity(resolver: "{$this->qualifyTestResolver('complexity')}")
        }
GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertGraphQLErrorMessage(QueryComplexity::maxQueryComplexityErrorMessage($max, self::CUSTOM_COMPLEXITY));
    }

    public function testResolvesComplexityResolverThroughDefaultNamespace(): void
    {
        $max = 1;
        $this->setMaxQueryComplexity($max);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo: Int @complexity(resolver: "Foo@complexity")
        }
GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertGraphQLErrorMessage(QueryComplexity::maxQueryComplexityErrorMessage($max, Foo::THE_ANSWER));
    }

    public function complexity(): int
    {
        return self::CUSTOM_COMPLEXITY;
    }

    protected function setMaxQueryComplexity(int $max): void
    {
        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.security.max_query_complexity', $max);
    }
}
