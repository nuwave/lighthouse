<?php

namespace Tests\Unit\Schema\Directives;

use GraphQL\Validator\Rules\QueryComplexity;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Tests\TestCase;
use Tests\Utils\Queries\Foo;

final class ComplexityDirectiveTest extends TestCase
{
    public const CUSTOM_COMPLEXITY = 123;

    public function testDefaultComplexity(): void
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

    public function testMaintainsDefaultBehaviour(): void
    {
        // TODO reenable in v6
        self::markTestSkipped('not respecting the cost of a field itself right now');

        // @phpstan-ignore-next-line unreachable
        $max = 1;
        $this->setMaxQueryComplexity($max);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            posts: [Post!]!
                @complexity
                @all
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

        // TODO add 1 for the field posts in v6
        // + 2 for data & title * 10 first items
        $expectedCount = 20;

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

    public function testIgnoresFirstArgumentUnrelatedToPagination(): void
    {
        $max = 1;
        $this->setMaxQueryComplexity($max);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            posts(first: String!): [Post!]! @all
        }

        type Post {
            title: String
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            posts(first: "named like the generated argument of @paginate, but should not increase complexity here") {
                title
            }
        }
        ')->assertGraphQLErrorMessage(QueryComplexity::maxQueryComplexityErrorMessage($max, 2));
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
