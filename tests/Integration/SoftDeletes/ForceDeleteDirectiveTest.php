<?php

namespace Tests\Integration\SoftDeletes;

use Nuwave\Lighthouse\SoftDeletes\ForceDeleteDirective;
use Nuwave\Lighthouse\SoftDeletes\SoftDeletesServiceProvider;
use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Nuwave\Lighthouse\Exceptions\DirectiveException;

class ForceDeleteDirectiveTest extends DBTestCase
{
    protected function getPackageProviders($app)
    {
        return array_merge(
            parent::getPackageProviders($app),
            [SoftDeletesServiceProvider::class]
        );
    }

    /**
     * @test
     */
    public function itForceDeletesTaskAndReturnsIt(): void
    {
        factory(Task::class)->create();

        $this->schema = '
        type Task {
            id: ID!
        }
        
        type Mutation {
            forceDeleteTask(id: ID!): Task @forceDelete
        }
        '.$this->placeholderQuery();

        $this->graphQL('
        mutation {
            forceDeleteTask(id: 1) {
                id
            }
        }
        ')->assertJson([
            'data' => [
                'forceDeleteTask' => [
                    'id' => 1,
                ],
            ],
        ]);

        $this->assertCount(0, Task::withTrashed()->get());
    }

    /**
     * @test
     */
    public function itForceDeletesDeletedTaskAndReturnsIt(): void
    {
        $task = factory(Task::class)->create();
        $task->delete();

        $this->schema = '
        type Task {
            id: ID!
        }
        
        type Mutation {
            forceDeleteTask(id: ID!): Task @forceDelete
        }
        '.$this->placeholderQuery();

        $this->graphQL('
        mutation {
            forceDeleteTask(id: 1) {
                id
            }
        }
        ')->assertJson([
            'data' => [
                'forceDeleteTask' => [
                    'id' => 1,
                ],
            ],
        ]);

        $this->assertCount(0, Task::withTrashed()->get());
    }

    /**
     * @test
     */
    public function itForceDeletesMultipleTasksAndReturnsThem(): void
    {
        factory(Task::class, 2)->create();

        $this->schema = '
        type Task {
            id: ID!
            name: String
        }
        
        type Mutation {
            forceDeleteTasks(id: [ID!]!): [Task!]! @forceDelete
        }
        '.$this->placeholderQuery();

        $this->graphQL('
        mutation {
            forceDeleteTasks(id: [1, 2]) {
                name
            }
        }
        ')->assertJsonCount(2, 'data.forceDeleteTasks');

        $this->assertCount(0, Task::withTrashed()->get());
    }

    /**
     * @test
     */
    public function itRejectsDefinitionWithNullableArgument(): void
    {
        $this->expectException(DirectiveException::class);

        $this->buildSchema('
        type Task {
            id: ID!
        }
        
        type Query {
            deleteTask(id: ID): Task @forceDelete
        }
        ');
    }

    /**
     * @test
     */
    public function itRejectsDefinitionWithNoArgument(): void
    {
        $this->expectException(DirectiveException::class);

        $this->buildSchema('
        type Task {
            id: ID!
        }
        
        type Query {
            deleteTask: Task @forceDelete
        }
        ');
    }

    /**
     * @test
     */
    public function itRejectsDefinitionWithMultipleArguments(): void
    {
        $this->expectException(DirectiveException::class);

        $this->buildSchema('
        type Task {
            id: ID!
        }
        
        type Query {
            deleteTask(foo: String, bar: Int): Task @forceDelete
        }
        ');
    }

    /**
     * @test
     */
    public function itRejectsUsingDirectiveWithNoSoftDeleteModels(): void
    {
        $this->expectExceptionMessage(ForceDeleteDirective::MODEL_NOT_USING_SOFT_DELETES);
        $this->buildSchema('
        type User {
            id: ID!
        }
        
        type Query {
            deleteUser(id: ID!): User @forceDelete
        }
        ');
    }
}
