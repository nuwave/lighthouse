<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Execution\MutationExecutor;
use Illuminate\Database\Eloquent\Relations\Relation;

class UpdateDirective extends MutationExecutorDirective
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'update';
    }

    /**
     * Execute an update mutation.
     *
     * @param  \Illuminate\Database\Eloquent\Model $modelInstance
     *         An empty instance of the model that should be updated
     * @param  \Illuminate\Support\Collection  $args
     *         The corresponding slice of the input arguments for updating this model
     * @param  \Illuminate\Database\Eloquent\Relations\Relation|null  $parentRelation
     *         If we are in a nested update, we can use this to associate the new model to its parent
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function executeMutation(Model $model, Collection $args, ?Relation $parentRelation = null): Model
    {
        return MutationExecutor::executeUpdate($model, new Collection($args))->refresh();
    }

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'SDL'
"""
Update an Eloquent model with the input values of the field.
"""
directive @update(
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
}
