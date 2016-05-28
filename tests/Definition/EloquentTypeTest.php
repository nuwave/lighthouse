<?php

namespace Nuwave\Lighthouse\Tests\Definition;

use Nuwave\Lighthouse\Tests\Support\GraphQL\Queries\UserQuery;
use Nuwave\Lighthouse\Tests\Support\Models\User;
use Nuwave\Lighthouse\Tests\DBTestCase;
use Nuwave\Lighthouse\Support\Definition\EloquentType;
use Nuwave\Lighthouse\Support\Cache\FileStore;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\IDType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\StringType;

class EloquentTypeTest extends DBTestCase
{
    /**
     * Set up environment.
     *
     * @param  \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function getEnvironmentSetup($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('lighthouse.cache', __DIR__.'/../Support/storage/cache');
    }

    /**
     * @test
     */
    public function itCanConvertModelToType()
    {
        $graphql = app('graphql');
        $graphql->cache()->flush();

        $user = new User;
        $eloquentType = new EloquentType($user, 'user');
        $type = $eloquentType->toType();
        $this->assertInstanceOf(ObjectType::class, $type);

        $fields = is_callable($type->config['fields']) ? $type->config['fields']() : $type->config['fields'];
        $fieldKeys = array_keys($fields);

        $this->assertEquals('User', $type->name);
        $this->assertEquals([
            'id', 'name', 'email', 'created_at', 'updated_at',
        ], $fieldKeys);
        $this->assertInstanceOf(IDType::class, $this->getFieldType('id', $fields));
        $this->assertInstanceOf(StringType::class, $this->getFieldType('name', $fields));
        $this->assertInstanceOf(StringType::class, $this->getFieldType('email', $fields));
        $this->assertInstanceOf(StringType::class, $this->getFieldType('created_at', $fields));
        $this->assertInstanceOf(StringType::class, $this->getFieldType('updated_at', $fields));
    }

    /**
     * @test
     */
    public function itShouldUseCacheToPopulateFields()
    {
        $initCache = new FileStore;
        $user = new User;

        $initType = new EloquentType($user, 'user');
        $initType->schemaFields();
        $initCache->store('user', $initType->getFields());

        $cache = $this->prophesize(FileStore::class);
        $this->app->instance(FileStore::class, $cache->reveal());
        $cache->get('User')->willReturn(collect(['foo' => 'bar']));
        $cache->store()->shouldNotBeCalled();

        $eloquentType = new EloquentType($user, 'user');
        $eloquentType->toType();
    }

    /**
     * @test
     * @group failing
     */
    public function itCanQueryEloquentType()
    {
        $query = '{
            userQuery {
                name
            }
        }';

        $expected = [
            'userQuery' => [
                'name' => 'foo',
            ]
        ];

        $graphql = app('graphql');
        $graphql->schema()->type('user', User::class);
        $graphql->schema()->query('userQuery', UserQuery::class);

        $this->assertEquals(['data' => $expected], $this->executeQuery($query));
    }

    /**
     * Extract type from field.
     *
     * @param  string $key
     * @param  array $fields
     * @return Type
     */
    protected function getFieldType($key, array $fields)
    {
        return isset($fields[$key]) ? $fields[$key]['type'] : null;
    }
}
