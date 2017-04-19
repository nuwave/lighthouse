<?php

namespace Nuwave\Lighthouse\Tests\Support\GraphQL\Types;

use GraphQL;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Support\Definition\GraphQLType;

class TaskType extends GraphQLType
{
    /**
     * Attributes of type.
     *
     * @var array
     */
    protected $attributes = [
        'name' => 'Task',
        'description' => 'A user task.',
    ];

    /**
     * Type fields.
     *
     * @return array
     */
    public function fields()
    {
        return [
            'title' => [
                'type' => Type::string(),
                'description' => 'Title of task.',
            ],
            'description' => [
                'type' => Type::string(),
                'description' => 'Description of task.',
            ],
            'completed' => [
                'type' => Type::boolean(),
                'description' => 'Completed status.',
            ],
            'user' => [
                'type' => GraphQL::type('user'),
                'description' => 'User who owns task.',
            ],
        ];
    }
}
