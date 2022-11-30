<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;

class DeleteDirective extends ModifyModelExistenceDirective implements ArgResolver, ArgManipulator
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Delete one or more models by their ID.
The field must have a single non-null argument that may be a list.
"""
directive @delete(
  """
  DEPRECATED use @globalId, will be removed in v6

  Set to `true` to use global ids for finding the model.
  If set to `false`, regular non-global ids are used.
  """
  globalId: Boolean = false

  """
  Specify the class name of the model to use.
  This is only needed when the default model detection does not work.
  """
  model: String

  """
  Specify the name of the relation on the parent model.
  This is only needed when using this directive as a nested arg
  resolver and if the name of the relation is not the arg name.
  """
  relation: String
) on FIELD_DEFINITION | ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    protected function find(string $modelClass, $idOrIds)
    {
        return $modelClass::find($idOrIds);
    }

    protected function modifyExistence(Model $model): bool
    {
        return (bool) $model->delete();
    }

    /**
     * Delete on ore more related models.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @param  mixed|array<mixed>  $idOrIds
     */
    public function __invoke($parent, $idOrIds): void
    {
        $relationName = $this->directiveArgValue(
            'relation',
            // Use the name of the argument if no explicit relation name is given
            $this->nodeName()
        );
        /** @var \Illuminate\Database\Eloquent\Relations\Relation $relation */
        $relation = $parent->{$relationName}();

        // Those types of relations may only have one related model attached to
        // it, so we don't need to use an ID to know which model to delete.
        $relationIsHasOneLike = $relation instanceof HasOne || $relation instanceof MorphOne;
        // This includes MorphTo, which is a subclass of BelongsTo
        $relationIsBelongsToLike = $relation instanceof BelongsTo;

        if ($relationIsHasOneLike || $relationIsBelongsToLike) {
            /** @var \Illuminate\Database\Eloquent\Relations\HasOne|\Illuminate\Database\Eloquent\Relations\MorphOne|\Illuminate\Database\Eloquent\Relations\BelongsTo $relation */
            // Only delete if the given value is truthy, since
            // the client might use a variable and always pass the argument.
            // Deleting when `false` is given seems wrong.
            if ($idOrIds) {
                if ($relationIsBelongsToLike) {
                    /** @var \Illuminate\Database\Eloquent\Relations\BelongsTo $relation */
                    $relation->dissociate();
                    $relation->getParent()->save();
                }

                // @phpstan-ignore-next-line Builder mixin is not understood
                $relation->delete();
            }
        } else {
            /** @var \Illuminate\Database\Eloquent\Model $related */
            // @phpstan-ignore-next-line Relation&Builder mixin not recognized
            $related = $relation->make();
            $related::destroy($idOrIds);
        }
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
