<?php

namespace Nuwave\Lighthouse\Tests\Support\GraphQL\Types;

use GraphQL;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Support\Definition\GraphQLType;

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
            ],
            'tasks' => GraphQL::connection('task')
                ->args([
                    'order' => [
                        'type' => Type::string(),
                        'description' => 'Sort order of tasks.'
                    ]
                ])
                ->resolve(function ($parent, array $args) {
                    return $parent->tasks->transform(function ($task) {
                        return array_merge($task->toArray(), ['title' => 'foo']);
                    });
                })
                ->field()
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
