<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;

class DeleteDirective extends ModifyModelExistenceDirective implements DefinedDirective, ArgResolver, ArgManipulator
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

  """
  Specify the name of the relation on the parent model.
  This is only needed when using this directive as a nested arg
  resolver and if the name of the relation is not the arg name.
  """
  relation: String
) on FIELD_DEFINITION | ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
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

    /**
     * Delete on ore more related models.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @param  mixed|mixed[]  $idOrIds
     * @return void
     */
    public function __invoke($parent, $idOrIds): void
    {
        $relationName = $this->directiveArgValue('relation');
        /** @var \Illuminate\Database\Eloquent\Relations\Relation $relation */
        $relation = $parent->{$relationName}();

        $related = $relation->make();
        $related::destroy($idOrIds);
    }

    public function manipulateArgDefinition(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$argDefinition,
        FieldDefinitionNode &$parentField,
        ObjectTypeDefinitionNode &$parentType
    ) {
        if (! $this->directiveArgValue('relation')) {
            throw new DefinitionException(
                'The @delete directive requires the "relation" to be set when used as an argument resolver.'
            );
        }
    }
}
