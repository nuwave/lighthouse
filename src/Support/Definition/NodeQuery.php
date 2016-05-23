<?php

namespace Nuwave\Relay\Support\Definition;

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
     * @return Illuminate\Database\Eloquent\Model|array
     */
    public function resolve($root, array $args, ResolveInfo $info)
    {
        list($typeClass, $id) = $this->decodeGlobalId($args['id']);

        foreach (config('relay.schema.types') as $type => $class) {
            if ($typeClass == $class) {
                $objectType = app($typeClass);

                $model = $objectType->resolveById($id);

                if (is_array($model)) {
                    $model['graphqlType'] = $type;
                } elseif (is_object($model)) {
                    $model->graphqlType = $type;
                }

                return $model;
            }
        }

        return null;
    }
}
