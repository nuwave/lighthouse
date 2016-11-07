<?php

namespace Nuwave\Lighthouse\Tests\Support\GraphQL\Types;

use GraphQL;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Support\Definition\GraphQLType;
use Nuwave\Lighthouse\Tests\Support\GraphQL\Connections\UserConnection;

class CompanyType extends GraphQLType
{
    /**
     * Attributes of type.
     *
     * @var array
     */
    protected $attributes = [
        'name' => 'Company',
        'description' => 'A company.'
    ];

    /**
     * Type fields.
     *
     * @return array
     */
    public function fields()
    {
        return [
            'name' => [
                'type' => Type::string(),
                'description' => 'Name of company.'
            ],
            'users' => GraphQL::connection(new UserConnection)->field(),
        ];
    }
}
