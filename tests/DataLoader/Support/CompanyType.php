<?php

namespace Nuwave\Lighthouse\Tests\DataLoader\Support;

use GraphQL;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Support\Definition\GraphQLType;

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
