<?php

namespace Tests\Integration\Execution\DataLoader;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Execution\DataLoader\BatchLoader;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Tests\DBTestCase;
use Tests\Utils\BatchLoaders\UserLoader;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class BatchLoaderTest extends DBTestCase
{
    public function testCanResolveBatchedFieldsFromBatchedRequests(): void
    {
        $users = factory(User::class, 2)
            ->create()
            ->each(function (User $user): void {
                factory(Task::class, 3)->create([
                    'user_id' => $user->getKey(),
                ]);
            });

        $this->schema = '
        type Task {
            name: String
        }
        type User {
            name: String
            email: String
            tasks: [Task] @hasMany
        }

        type Query {
            user(id: ID! @eq): User @find
        }
        ';

        $query = '
        query User($id: ID!) {
            user(id: $id) {
                email
                tasks {
                    name
                }
            }
        }
        ';

        $this
            ->postGraphQL([
                [
                    'query' => $query,
                    'variables' => [
                        'id' => $users[0]->getKey(),
                    ],
                ],
                [
                    'query' => $query,
                    'variables' => [
                        'id' => $users[1]->getKey(),
                    ],
                ],
            ])
            ->assertJsonCount(2)
            ->assertJsonCount(3, '0.data.user.tasks')
            ->assertJsonCount(3, '1.data.user.tasks');
    }

    public function testCanResolveFieldsByCustomBatchLoader(): void
    {
        $users = factory(User::class, 3)
            ->create()
            ->each(function (User $user): void {
                factory(Task::class, 3)->create([
                    'user_id' => $user->getKey(),
                ]);
            });

        $this->schema = '
        type Task {
            name: String
        }
        type User {
            name: String
            email: String
            tasks: [Task] @hasMany
        }

        type Query {
            user(id: ID!): User 
                @field(resolver: "'.$this->qualifyTestResolver('resolveUser').'")
            manyUsers(ids: [ID!]!): [User] 
                @field(resolver: "'.$this->qualifyTestResolver('resolveManyUsers').'")
        }
        ';

        $query = '
        query User($id: ID!, $ids: [ID!]!) {
            user(id: $id) {
                email
                tasks {
                    name
                }
            }
            manyUsers(ids: $ids) {
                email
                tasks {
                    name
                }
            }
        }
        ';

        $this
            ->postGraphQL([
                'query' => $query,
                'variables' => [
                    'id' => $users[0]->getKey(),
                    'ids' => [$users[1]->getKey(), $users[2]->getKey()],
                ],
            ])
            ->assertJsonCount(2, 'data.manyUsers')
            ->assertJsonCount(3, 'data.manyUsers.0.tasks')
            ->assertJsonCount(3, 'data.manyUsers.1.tasks')
            ->assertJsonCount(3, 'data.user.tasks');
    }

    public function resolveUser($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        $loader = BatchLoader::instance(UserLoader::class, $info->path);

        return $loader->load($args['id']);
    }

    public function resolveManyUsers($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        $loader = BatchLoader::instance(UserLoader::class, $info->path);

        return $loader->loadMany($args['ids']);
    }
}
