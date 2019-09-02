<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;

class RestoreDirective extends ModifyModelExistenceDirective implements DefinedDirective
{
    protected $verifySoftDeletesUsed = true;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'restore';
    }

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'SDL'
"""
Un-delete one or more soft deleted models by their ID. 
The field must have a single non-null argument that may be a list.
"""
directive @restore(
  """
  Set to `true` to use global ids for finding the model.
  If set to `false`, regular non-global ids are used.
  """
  globalId: Boolean = false

  """
  Specify the class name of the model to use.
  This is only needed when the default model resolution does not work.
  """
  model: String
) on FIELD_DEFINITION
SDL;
    }

    /**
     * Find one or more models by id.
     *
     * @param string|\Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\SoftDeletes $modelClass
     * @param string|int|string[]|int[] $idOrIds
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Collection
     */
    protected function find(string $modelClass, $idOrIds)
    {
        return $modelClass::withTrashed()->find($idOrIds);
    }

    /**
     * Bring a model in or out of existence.
     *
     * @param \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\SoftDeletes $model
     * @return void
     */
    protected function modifyExistence(Model $model): void
    {
        $model->restore();
    }
}
