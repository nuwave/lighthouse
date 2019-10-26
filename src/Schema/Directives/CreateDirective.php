<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Execution\Arguments\ArgResolver;
use Nuwave\Lighthouse\Execution\Arguments\SaveModel;

class CreateDirective extends MutationExecutorDirective
{
    public function name(): string
    {
        return 'create';
    }

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'SDL'
"""
Create a new Eloquent model with the given arguments.
"""
directive @create(  
  """
  Specify the class name of the model to use.
  This is only needed when the default model resolution does not work.
  """
  model: String
) on FIELD_DEFINITION
SDL;
    }

    protected function executeMutation(Model $model, $args, $context, ResolveInfo $resolveInfo): Model
    {
        $saveModel = new ArgResolver(new SaveModel());
        $model = $saveModel($model, $resolveInfo->argumentSet);

        return $model;
    }
}
