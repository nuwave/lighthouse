<?php declare(strict_types=1);

namespace Tests\Unit\Schema\Directives;

use GraphQL\Validator\Rules\QueryComplexity;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Nuwave\Lighthouse\Events\BuildExtensionsResponse;
use Tests\TestCase;
use Tests\Utils\Queries\Foo;

final class ComplexityDirectiveTest extends TestCase
{
    public const CUSTOM_COMPLEXITY = 123;

    public function testDefaultComplexity(): void
    {
        $eventsDispatcher = $this->app->make(EventsDispatcher::class);

        /** @var array<int, BuildExtensionsResponse> $events */
        $events = [];
        $eventsDispatcher->listen(BuildExtensionsResponse::class, static function (BuildExtensionsResponse $event) use (&$events): void {
            $events[] = $event;
        });

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

        $this->assertCount(1, $events);

        $event = $events[0];
        $this->assertSame(2, $event->queryComplexity);
    }

    public function testKnowsPagination(): void
    {
        $max = 1;
        $this->setMaxQueryComplexity($max);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            posts: [Post!]! @paginate
        }

        type Post {
            title: String
        }
        ';

        // 1 + (2 for data & title * 10 first items)
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

    public static function complexity(): int
    {
        return self::CUSTOM_COMPLEXITY;
    }

    protected function setMaxQueryComplexity(int $max): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.security.max_query_complexity', $max);
    }
}
