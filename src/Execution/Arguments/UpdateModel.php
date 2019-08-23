<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use Nuwave\Lighthouse\Schema\Context;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Execution\Resolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class UpdateModel implements Resolver
{
    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param $args
     * @param \Nuwave\Lighthouse\Schema\Context $context
     * @param \GraphQL\Type\Definition\ResolveInfo $resolveInfo
     */
    public function __invoke($model, $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $id = $args['id']
            ?? $args[$model->getKeyName()];

        $model = $model->newQuery()->findOrFail($id);

        return (new SaveModel)($model, $args, $context, $resolveInfo);
    }
}
