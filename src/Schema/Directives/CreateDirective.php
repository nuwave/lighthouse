<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Execution\MutationExecutor;

class CreateDirective extends MutationExecutorDirective
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'create';
    }

    /**
     * Execute a create mutation.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     *         An empty instance of the model that should be created
     * @param  \Illuminate\Support\Collection  $args
     *         The corresponding slice of the input arguments for creating this model
     * @param  \Illuminate\Database\Eloquent\Relations\Relation|null  $parentRelation
     *         If we are in a nested create, we can use this to associate the new model to its parent
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function executeMutation(Model $model, Collection $args, ?Relation $parentRelation = null): Model
    {
        return MutationExecutor::executeCreate($model, $args, $parentRelation);
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
}
