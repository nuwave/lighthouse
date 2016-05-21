<?php

namespace Nuwave\Relay\Tests\Support\Types;

use GraphQL\Type\Definition\Type;
use Nuwave\Relay\Support\Definition\GraphQLType;

class UserType extends GraphQLType
{
    /**
     * Attributes of type.
     *
     * @var array
     */
    protected $attributes = [
        'name' => 'User',
        'description' => 'A user.'
    ];

    /**
     * Type fields.
     *
     * @return array
     */
    public function fields()
    {
        return [
            'id' => [
                'type' => Type::nonNull(Type::string()),
                'description' => 'ID of the user.'
            ],
            'name' => [
                'type' => Type::string(),
                'description' => 'Name of the user.'
            ],
            'email' => [
                'type' => Type::string(),
                'description' => 'Email of the user.'
            ]
        ];
    }

    /**
     * Resolve user email.
     *
     * @param  mixed $root
     * @param  array  $args
     * @return string
     */
    protected function resolveEmailField($root, array $args)
    {
        return 'foo@bar.com';
    }
}
