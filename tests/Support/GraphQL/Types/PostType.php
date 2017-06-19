<?php

namespace Nuwave\Lighthouse\Tests\Support\GraphQL\Types;

use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Tests\Support\Models\Post;
use Nuwave\Lighthouse\Support\Interfaces\RelayType;
use Nuwave\Lighthouse\Support\Definition\GraphQLType;

class PostType extends GraphQLType implements RelayType
{
    /**
     * Attributes of type.
     *
     * @var array
     */
    protected $attributes = [
        'name' => 'Post',
        'description' => 'A post that does not have a regular id field.',
    ];

    /**
     * Get model by id.
     *
     * Note: When the root 'node' query is called, this method
     * will be used to resolve the type by providing the id.
     *
     * @param  mixed $id
     * @return mixed
     */
    public function resolveById($id)
    {
        return factory(Post::class)->make([
            'post_id' => $id,
            'title' => 'Foobar',
        ]);
    }

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
                'description' => 'Title of the post.',
            ],
            'content' => [
                'type' => Type::string(),
                'description' => 'Content of the post.',
            ],
            'user_id' => [
                'type' => Type::int(),
                'description' => 'ID of user who wrote the post.',
            ],
        ];
    }
}
