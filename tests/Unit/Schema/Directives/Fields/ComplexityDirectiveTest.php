<?php

namespace Tests\Unit\Schema\Directives\Fields;

use Tests\TestCase;

class ComplexityDirectiveTest extends TestCase
{
    /**
     * @test
     */
    public function itCanSetDefaultComplexityOnField()
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
        $this->assertSame(100, $complexityFn(10, ['count' => 10]));
    }

    /**
     * @test
     */
    public function itCanSetCustomComplexityResolver()
    {
        $resolver = addslashes(self::class);

        $schema = $this->buildSchemaWithPlaceholderQuery('
        type User {
            posts: [Post!]!
                @complexity(resolver: "'.$resolver.'@complexity")
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
    public function itResolvesComplexityResolverThroughDefaultNamespace()
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
        return $childrenComplexity * array_get($args, 'foo', 0);
    }
}
