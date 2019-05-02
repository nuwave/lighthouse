<?php

namespace Tests\Unit\Schema\Directives;

use Tests\TestCase;
use Illuminate\Support\Arr;

class ComplexityDirectiveTest extends TestCase
{
    /**
     * @test
     */
    public function itCanSetDefaultComplexityOnField(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery('
        type User {
            posts: [Post!]! @complexity @hasMany
        }
        
        type Post {
            title: String
        }
        ');

        $complexityFn = $schema->getType('User')
            ->getField('posts')
            ->getComplexityFn();

        $this->assertSame(100, $complexityFn(10, ['first' => 10]));
        $this->assertSame(100, $complexityFn(10, [config('lighthouse.pagination_amount_argument') => 10]));
    }

    /**
     * @test
     */
    public function itCanSetCustomComplexityResolver(): void
    {
        $schema = $this->buildSchemaWithPlaceholderQuery('
        type User {
            posts: [Post!]!
                @complexity(resolver: "'.$this->qualifyTestResolver('complexity').'")
                @hasMany
        }
        
        type Post {
            title: String
        }
        ');

        $complexityFn = $schema->getType('User')
            ->getField('posts')
            ->getComplexityFn();

        $this->assertSame(100, $complexityFn(10, ['foo' => 10]));
    }

    /**
     * @test
     */
    public function itResolvesComplexityResolverThroughDefaultNamespace(): void
    {
        $schema = $this->buildSchema('
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

    public function complexity(int $childrenComplexity, array $args): int
    {
        return $childrenComplexity * Arr::get($args, 'foo', 0);
    }
}
