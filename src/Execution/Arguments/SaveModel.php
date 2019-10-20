<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Execution\Resolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class SaveModel implements Resolver
{
    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param $args
     * @param \Nuwave\Lighthouse\Schema\Context $context
     * @param \GraphQL\Type\Definition\ResolveInfo $resolveInfo
     */
    public function __invoke($model, $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        ($resolveInfo->resolveBeforeResolvers)($model);

        $model->fill($args);
        $model->save();

        return $model;
    }
}
