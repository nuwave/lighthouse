<?php

namespace Nuwave\Lighthouse\Schema\Types;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Model;

class ModelInput
{
    /**
     * Handle whereNotNull input.
     *
     * @param Model       $model
     * @param array       $args
     * @param mixed       $context
     * @param ResolveInfo $info
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function whereNotNull(Model $model, array $args, $context, $info)
    {
        dd($args);
    }
}
