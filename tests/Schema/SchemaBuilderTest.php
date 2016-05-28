<?php

namespace Nuwave\Lighthouse\Tests\Schema;

use Nuwave\Lighthouse\Tests\TestCase;
use Nuwave\Lighthouse\Tests\Support\GraphQL\Types\UserType;
use Nuwave\Lighthouse\Tests\Support\GraphQL\Types\TaskType;
use Nuwave\Lighthouse\Tests\Support\GraphQL\Queries\UserQuery;
use GraphQL\Type\Definition\ObjectType;

class SchemaBuilderTest extends TestCase
{
    /**
     * @test
     */
    public function itCanGroupElementsByNamespace()
    {
        $graphql = app('graphql');
        $namespace = 'Nuwave\\Lighthouse\\Tests\\Support\\GraphQL\\Types';

        $graphql->schema()->group(['namespace' => $namespace], function () use ($graphql) {
            $graphql->schema()->type('userGrouped', 'UserType');
            $graphql->schema()->type('taskGrouped', 'TaskType');
        });

        $this->assertInstanceOf(ObjectType::class, $graphql->type('userGrouped'));
        $this->assertInstanceOf(ObjectType::class, $graphql->type('taskGrouped'));
    }

    /**
     * @test
     */
    public function itCanAttachMiddlewareToQueries()
    {
        $query = '{
            userQuery {
                name
            }
        }';

        $graphql = app('graphql');
        $graphql->schema()->type('user', UserType::class);
        $graphql->schema()->type('task', TaskType::class);
        $graphql->schema()->group(['middleware' => ['auth']], function () use ($graphql) {
            $graphql->schema()->query('userQuery', UserQuery::class)
                ->middleware('throttle');
        });

        $middleware = $graphql->schema()->parse($query)->middleware();
        $this->assertCount(2, $middleware);
        $this->assertContains('auth', $middleware->keys());
        $this->assertContains('throttle', $middleware->keys());
    }

    /**
     * @test
     */
    public function itAttachesMiddlewareToMutations()
    {
        $query = 'mutation UpdateUserEmail {
            updateEmail(id: "foo", email: "foo@bar.com") {
                email
            }
        }';

        $graphql = app('graphql');
        $graphql->schema()->type('user', UserType::class);
        $graphql->schema()->type('task', TaskType::class);
        $graphql->schema()->group(['middleware' => 'auth'], function () use ($graphql) {
            $graphql->schema()->mutation('updateEmail', UpdateEmailMutation::class)
                ->middleware('throttle');
        });

        $middleware = $graphql->schema()->parse($query)->middleware();
        $this->assertCount(2, $middleware);
        $this->assertContains('auth', $middleware->keys());
        $this->assertContains('throttle', $middleware->keys());
    }
}
