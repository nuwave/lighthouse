<?php declare(strict_types=1);

namespace Tests\Integration\Cache;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Tests\TestCase;

final class ClearCacheDirectiveTest extends TestCase
{
    protected CacheRepository $cache;

    protected function setUp(): void
    {
        parent::setUp();

        config(['lighthouse.cache_directive_tags' => true]);

        $this->cache = $this->app->make(CacheRepository::class);
    }

    public function testClearCacheForEntireType(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Mutation {
            foo: Int! @clearCache(type: "Foo")
        }
        GRAPHQL . self::PLACEHOLDER_QUERY;

        $taggedCache = $this->cache->tags(['lighthouse:Foo:']);

        $key = 'foo';
        $taggedCache->set($key, 'some-value');

        $this->assertTrue($taggedCache->has($key));

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            foo
        }
        GRAPHQL)->assertGraphQLErrorFree();

        $this->assertFalse($taggedCache->has($key));
    }

    public function testClearCacheForAllFieldsOfType(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Mutation {
            foo: Int! @clearCache(type: "Foo", field: "bar")
        }
        GRAPHQL . self::PLACEHOLDER_QUERY;

        $taggedCache = $this->cache->tags(['lighthouse:Foo::bar']);

        $key = 'foo';
        $taggedCache->set($key, 'some-value');

        $this->assertTrue($taggedCache->has($key));

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            foo
        }
        GRAPHQL)->assertGraphQLErrorFree();

        $this->assertFalse($taggedCache->has($key));
    }

    public function testClearCacheForTypeWithIDByArgument(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Mutation {
            foo(id: ID!): Int! @clearCache(type: "Foo", idSource: { argument: "id" })
        }
        GRAPHQL . self::PLACEHOLDER_QUERY;

        $taggedCache = $this->cache->tags(['lighthouse:Foo:1']);

        $key = 'foo';
        $taggedCache->set($key, 'some-value');

        $this->assertTrue($taggedCache->has($key));

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            foo(id: 1)
        }
        GRAPHQL)->assertGraphQLErrorFree();

        $this->assertFalse($taggedCache->has($key));
    }

    public function testClearCacheForTypeWithIDByArgumentNestedPath(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        input FooInput {
            id: ID!
        }

        type Mutation {
            foo(input: FooInput!): Int! @clearCache(type: "Foo", idSource: { argument: "input.id" })
        }
        GRAPHQL . self::PLACEHOLDER_QUERY;

        $taggedCache = $this->cache->tags(['lighthouse:Foo:1']);

        $key = 'foo';
        $taggedCache->set($key, 'some-value');

        $this->assertTrue($taggedCache->has($key));

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            foo(input: { id: 1 })
        }
        GRAPHQL)->assertGraphQLErrorFree();

        $this->assertFalse($taggedCache->has($key));
    }

    public function testClearCacheForTypeWithIDByField(): void
    {
        $this->mockResolver([
            'bar' => 2,
        ]);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Foo {
            bar: Int!
        }

        type Mutation {
            foo: Foo!
                @mock
                @clearCache(type: "Foo", idSource: { field: "bar" })
        }
        GRAPHQL . self::PLACEHOLDER_QUERY;

        $taggedCache = $this->cache->tags(['lighthouse:Foo:2']);

        $key = 'foo';
        $taggedCache->set($key, 'some-value');

        $this->assertTrue($taggedCache->has($key));

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            foo {
                bar
            }
        }
        GRAPHQL)->assertGraphQLErrorFree();

        $this->assertFalse($taggedCache->has($key));
    }

    public function testClearCacheForMultipleTypesWithIDByField(): void
    {
        $this->mockResolver(static fn (): array => [
            [
                'bar' => 1,
            ],
            null,
            [
                'bar' => 2,
            ],
        ]);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Foo {
            bar: Int!
        }

        type Mutation {
            foos: [Foo]!
                @mock
                @clearCache(type: "Foo", idSource: { field: "*.bar" })
        }
        GRAPHQL . self::PLACEHOLDER_QUERY;

        $taggedCache1 = $this->cache->tags(['lighthouse:Foo:1']);
        $taggedCache2 = $this->cache->tags(['lighthouse:Foo:2']);

        $key = 'foo';
        $taggedCache1->set($key, 'some-value');
        $taggedCache2->set($key, 'some-value');

        $this->assertTrue($taggedCache1->has($key));
        $this->assertTrue($taggedCache2->has($key));

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            foos {
                bar
            }
        }
        GRAPHQL)->assertGraphQLErrorFree();

        $this->assertFalse($taggedCache1->has($key));
        $this->assertFalse($taggedCache2->has($key));
    }

    public function testClearCacheForTypeWithIDByFieldNestedPath(): void
    {
        $this->mockResolver([
            'bar' => [
                'baz' => 3,
            ],
        ]);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Bar {
            baz: Int!
        }

        type Foo {
            bar: Bar!
        }

        type Mutation {
            foo: Foo!
                @mock
                @clearCache(type: "Foo", idSource: { field: "bar.baz" })
        }
        GRAPHQL . self::PLACEHOLDER_QUERY;

        $taggedCache = $this->cache->tags(['lighthouse:Foo:3']);

        $key = 'foo';
        $taggedCache->set($key, 'some-value');

        $this->assertTrue($taggedCache->has($key));

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            foo {
                bar {
                    baz
                }
            }
        }
        GRAPHQL)->assertGraphQLErrorFree();

        $this->assertFalse($taggedCache->has($key));
    }

    public function testClearCacheForTypeWithIDByArgumentForField(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Mutation {
            foo(id: ID!): Int! @clearCache(type: "Foo", idSource: { argument: "id" }, field: "baz")
        }
        GRAPHQL . self::PLACEHOLDER_QUERY;

        $taggedCache = $this->cache->tags(['lighthouse:Foo:1:baz']);

        $key = 'foo';
        $taggedCache->set($key, 'some-value');

        $this->assertTrue($taggedCache->has($key));

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            foo(id: 1)
        }
        GRAPHQL)->assertGraphQLErrorFree();

        $this->assertFalse($taggedCache->has($key));
    }
}
