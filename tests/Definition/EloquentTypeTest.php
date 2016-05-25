<?php

namespace Nuwave\Relay\Tests\Definition;

use Nuwave\Relay\Tests\Support\Models\User;
use Nuwave\Relay\Tests\DBTestCase;
use Nuwave\Relay\Support\Definition\EloquentType;
use Nuwave\Relay\Support\Cache\FileStore;
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

        $app['config']->set('relay.cache', __DIR__.'/../Support/storage/cache');
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

        $fields = $type->config['fields'];
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
