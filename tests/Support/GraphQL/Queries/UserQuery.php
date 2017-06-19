<?php

namespace Nuwave\Lighthouse\Tests\Support\GraphQL\Queries;

use GraphQL;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Tests\Support\Models\Task;
use Nuwave\Lighthouse\Tests\Support\Models\User;
use Nuwave\Lighthouse\Support\Definition\GraphQLQuery;

class UserQuery extends GraphQLQuery
{
    /**
     * Type query returns.
     *
     * @return Type
     */
    public function type()
    {
        return GraphQL::type('user');
    }

    /**
     * Available query arguments.
     *
     * @return array
     */
    public function args()
    {
        return [
            'id' => [
                'type' => Type::string(),
                'description' => 'ID of the user.',
            ],
        ];
    }

    /**
     * Resolve the query.
     *
     * @param  mixed  $root
     * @param  array  $args
     * @return mixed
     */
    public function resolve($root, array $args)
    {
        $user = factory(User::class)->make([
            'name' => 'foo',
        ]);

        $user->id = 1;
        $user->tasks = factory(Task::class, 5)->make();

        return $user;
    }
}
