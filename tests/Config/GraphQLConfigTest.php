<?php

namespace Nuwave\Relay\Tests\Config;

use Nuwave\Relay\Tests\TestCase;
use Nuwave\Relay\Tests\Support\GraphQL\Mutations\UpdateEmailMutation;
use Nuwave\Relay\Tests\Support\GraphQL\Types\UserType;
use Nuwave\Relay\Tests\Support\GraphQL\Queries\UserQuery;
use GraphQL\Type\Definition\ObjectType;

class GraphQLConfigTest extends TestCase
{
    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('relay.schema.register', function () {
            $graphql = app('graphql');
            $graphql->schema()->type('user', UserType::class);
            $graphql->schema()->query('userQuery', UserQuery::class);
            $graphql->schema()->mutation('updateEmail', UpdateEmailMutation::class);
        });
    }

    /**
     * @test
     */
    public function itCanRegisterWithConfig()
    {
        $graphql = app('graphql');
        $this->assertInstanceOf(ObjectType::class, $graphql->type('user'));
        $this->assertContains('userQuery', $graphql->queries()->keys());
        $this->assertContains('updateEmail', $graphql->mutations()->keys());
    }
}
