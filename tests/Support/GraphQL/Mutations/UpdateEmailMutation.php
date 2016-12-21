<?php

namespace Nuwave\Lighthouse\Tests\Support\GraphQL\Mutations;

use Nuwave\Lighthouse\Support\Definition\GraphQLMutation;
use Nuwave\Lighthouse\Tests\Support\Models\User;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class UpdateEmailMutation extends GraphQLMutation
{
    /**
     * Type that mutation returns.
     *
     * @return ObjectType
     */
    public function type()
    {
        return app('graphql')->type('user');
    }

    /**
     * Available arguments on mutation.
     *
     * @return array
     */
    public function args()
    {
        return [
            'id' => [
                'name' => 'id',
                'type' => Type::nonNull(Type::string()),
            ],
            'email' => [
                'name' => 'email',
                'type' => Type::nonNull(Type::string()),
                'rules' => ['email']
            ]
        ];
    }

    /**
     * Resolve the mutation.
     *
     * @param  mixed $root
     * @param  array  $args
     * @return mixed
     */
    public function resolve($root, array $args)
    {
        $user = factory(User::class)->make([
            'email' => 'foo@example.com'
        ]);

        $user->email = $args['email'];

        return $user;
    }
}
