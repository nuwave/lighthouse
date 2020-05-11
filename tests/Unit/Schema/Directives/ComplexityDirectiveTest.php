<?php

namespace Tests\Unit\Schema\Directives;

use Illuminate\Support\Arr;
use Tests\TestCase;

class ComplexityDirectiveTest extends TestCase
{
    public function testCanSetDefaultComplexityOnField(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ '
        type User {
            posts: [Post!]! @complexity @hasMany
        }

        type Post {
            title: String
        }
        ');

        /** @var \GraphQL\Type\Definition\ObjectType $user */
        $user = $schema->getType('User');
        $complexityFn = $user
            ->getField('posts')
            ->getComplexityFn();

        $this->assertSame(100, $complexityFn(10, ['first' => 10]));
        $this->assertSame(100, $complexityFn(10, [config('lighthouse.pagination_amount_argument') => 10]));
    }

    public function testCanSetCustomComplexityResolver(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ <<<GRAPHQL
        type User {
            posts: [Post!]!
                @complexity(resolver: "{$this->qualifyTestResolver('complexity')}")
                @hasMany
        }

        type Post {
            title: String
        }
GRAPHQL
        );

        /** @var \GraphQL\Type\Definition\ObjectType $user */
        $user = $schema->getType('User');
        $complexityFn = $user
            ->getField('posts')
            ->getComplexityFn();

        $this->assertSame(100, $complexityFn(10, ['foo' => 10]));
    }

    public function testResolvesComplexityResolverThroughDefaultNamespace(): void
    {
        $schema = $this->buildSchema(/** @lang GraphQL */ '
        type Query {
            foo: Int
                @complexity(resolver: "Foo@complexity")
        }
        ');

        $complexityFn = $schema->getQueryType()
            ->getField('foo')
            ->getComplexityFn();

        $this->assertSame(42, $complexityFn());
    }

    /**
     * @param  array<string, int|null>  $args
     */
    public function complexity(int $childrenComplexity, array $args): int
    {
        return $childrenComplexity * Arr::get($args, 'foo', 0);
    }
}
