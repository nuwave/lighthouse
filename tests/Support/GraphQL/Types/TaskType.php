<?php

namespace Nuwave\Relay\Tests\Support\GraphQL\Types;

use GraphQL\Type\Definition\Type;
use Nuwave\Relay\Support\Definition\GraphQLType;

class TaskType extends GraphQLType
{
    /**
     * Attributes of type.
     *
     * @var array
     */
    protected $attributes = [
        'name' => 'Task',
        'description' => 'A user task.'
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
                'description' => 'Title of task.'
            ],
            'description' => [
                'type' => Type::string(),
                'description' => 'Description of task.'
            ],
            'completed' => [
                'type' => Type::boolean(),
                'description' => 'Completed status.'
            ]
        ];
    }
}
