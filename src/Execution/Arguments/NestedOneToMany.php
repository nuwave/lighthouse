<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Execution\Resolver;
use Nuwave\Lighthouse\Execution\Arguments\ArgResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class NestedOneToMany implements Resolver
{
    /**
     * @var string
     */
    private $relationName;

    public function __construct(string $relationName)
    {
        $this->relationName = $relationName;
    }

    public function __invoke($model, $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        /** @var \Illuminate\Database\Eloquent\Relations\HasMany|\Illuminate\Database\Eloquent\Relations\MorphMany $relation */
        $relation = $model->{$this->relationName}();

        if (isset($args['create'])) {
            $saveModel = new ArgResolver(new SaveModel());

            foreach ($args['create'] as $childArgs) {
                $saveModel($relation->make(), $childArgs, $context, $resolveInfo);
            }
        }

        if (isset($args['update'])) {
            $updateModel = new ArgResolver(new UpdateModel());

            foreach ($args['update'] as $childArgs) {
                $updateModel($relation->make(), $childArgs, $context, $resolveInfo);
            }
        }

        if (isset($args['delete'])) {
            $relation->getRelated()::destroy($args['delete']);
        }
    }
}
