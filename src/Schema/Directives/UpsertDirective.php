<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Execution\Arguments\ArgResolver;
use Nuwave\Lighthouse\Execution\Arguments\SaveModel;
use Nuwave\Lighthouse\Execution\Arguments\UpsertModel;

class UpsertDirective extends MutationExecutorDirective
{
    public function name(): string
    {
        return 'upsert';
    }

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'SDL'
"""
Create or update an Eloquent model with the input values of the field.
"""
directive @upsert(
  """
  Specify the class name of the model to use.
  This is only needed when the default model resolution does not work.
  """
  model: String

  """
  Set to `true` to use global ids for finding the model.
  If set to `false`, regular non-global ids are used.
  """
  globalId: Boolean = false
) on FIELD_DEFINITION
SDL;
    }

    protected function executeMutation(Model $model, $args, $context, ResolveInfo $resolveInfo): Model
    {
        $upsert = new ArgResolver(new UpsertModel(new SaveModel()));
        $model = $upsert($model, $resolveInfo->argumentSet);

        return $model;
    }
}
