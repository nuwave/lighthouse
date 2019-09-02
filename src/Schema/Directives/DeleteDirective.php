<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;

class DeleteDirective extends ModifyModelExistenceDirective implements DefinedDirective
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'delete';
    }

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'SDL'
"""
Delete one or more models by their ID.
The field must have a single non-null argument that may be a list.
"""
directive @delete(
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
     * @param string|\Illuminate\Database\Eloquent\Model $modelClass
     * @param string|int|string[]|int[] $idOrIds
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Collection
     */
    protected function find(string $modelClass, $idOrIds)
    {
        return $modelClass::find($idOrIds);
    }

    /**
     * Bring a model in or out of existence.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    protected function modifyExistence(Model $model): void
    {
        $model->delete();
    }
}
