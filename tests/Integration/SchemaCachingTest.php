<?php

namespace Tests\Integration;

use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Tests\SerializingArrayStore;
use Tests\TestCase;
use Tests\Utils\Models\Comment;

class SchemaCachingTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $app['config'];

        $config->set('lighthouse.cache.enable', true);

        /** @var \Illuminate\Cache\CacheManager $cache */
        $cache = $app->make(CacheManager::class);
        $cache->extend('serializing-array', function () {
            return new Repository(
                new SerializingArrayStore()
            );
        });
        $config->set('cache.stores.array.driver', 'serializing-array');
    }

    public function testSchemaCachingWithUnionType(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Foo @mock
        }

        union Foo = Comment | Color

        type Comment {
            bar: ID
        }

        type Color {
            id: ID
        }
        ';
        $this->cacheSchema();

        $this->mockResolver(new Comment([
            'bar' => 'bar',
        ]));

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo {
                ... on Comment {
                    bar
                }
            }
        }
        ')->assertExactJson([
            'data' => [
                'foo' => [
                    'bar' => 'bar',
                ],
            ],
        ]);
    }

    protected function cacheSchema(): void
    {
        /** @var \Nuwave\Lighthouse\Schema\AST\ASTBuilder $astBuilder */
        $astBuilder = app(ASTBuilder::class);
        $astBuilder->documentAST();
        $this->app->forgetInstance(ASTBuilder::class);
    }
}
