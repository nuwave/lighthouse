<?php

namespace Nuwave\Lighthouse\Support\Definition;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

class NodeQuery extends GraphQLQuery
{
    /**
     * Associated GraphQL Type.
     *
     * @return mixed
     */
    public function type()
    {
        return app('graphql')->type('node');
    }

    /**
     * Arguments available on node query.
     *
     * @return array
     */
    public function args()
    {
        return [
            'id' => [
                'name' => 'id',
                'type' => Type::nonNull(Type::id())
            ]
        ];
    }

    /**
     * Resolve query.
     *
     * @param  string $root
     * @param  array $args
     * @param  mixed $context
     * @return Illuminate\Database\Eloquent\Model|array|null
     */
    public function resolve($root, array $args, $context, ResolveInfo $info)
    {
        list($typeClass, $id) = $this->decodeGlobalId($args['id']);

        return app('graphql')->types()->filter(function ($type) use ($typeClass) {
            return $typeClass === $type->namespace;
        })->transform(function ($type, $name) use ($id) {
            $model = app($type->namespace)->resolveById($id);

            if (is_array($model)) {
                $model['graphqlType'] = $name;
            } elseif (is_object($model)) {
                $model->graphqlType = $name;
            }

            return $model;
        })->first();
    }
}
