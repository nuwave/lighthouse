<?php

namespace Nuwave\Lighthouse\Tests\Support\GraphQL\Mutations;

use GraphQL;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Tests\Support\Models\User;
use Nuwave\Lighthouse\Support\Interfaces\RelayMutation;
use Nuwave\Lighthouse\Support\Definition\GraphQLMutation;

class UpdateEmailRelayMutation extends GraphQLMutation implements RelayMutation
{
    /**
     * Attributes of mutation.
     *
     * @var array
     */
    protected $attributes = [
        'name' => 'updateEmail',
    ];

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
                'rules' => ['email'],
            ],
        ];
    }

    /**
     * List of output fields.
     *
     * @return array
     */
    public function outputFields()
    {
        return [
            'user' => [
                'type' => GraphQL::type('user'),
                'resolve' => function ($user) {
                    return $user;
                },
            ],
        ];
    }

    /**
     * Resolve the mutation.
     *
     * @param  array  $args
     * @param  mixed  $context
     * @param  ResolveInfo $info
     * @return mixed
     */
    public function mutateAndGetPayload(array $args, $context, ResolveInfo $info)
    {
        $user = factory(User::class)->make([
            'email' => 'foo@example.com',
        ]);

        $user->email = $args['email'];

        return $user;
    }
}
