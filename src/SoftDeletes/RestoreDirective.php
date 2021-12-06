<?php

namespace Nuwave\Lighthouse\SoftDeletes;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\ModifyModelExistenceDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;

class RestoreDirective extends ModifyModelExistenceDirective implements FieldManipulator
{
    public const MODEL_NOT_USING_SOFT_DELETES = 'Use the @restore directive only for Model classes that use the SoftDeletes trait.';

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Un-delete one or more soft deleted models by their ID.
The field must have a single non-null argument that may be a list.
"""
directive @restore(
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
) on FIELD_DEFINITION
GRAPHQL;
    }

    protected function find(string $modelClass, $idOrIds)
    {
        /** @see \Illuminate\Database\Eloquent\SoftDeletes */
        // @phpstan-ignore-next-line because it involves mixins
        return $modelClass::withTrashed()->find($idOrIds);
    }

    protected function modifyExistence(Model $model): bool
    {
        /** @see \Illuminate\Database\Eloquent\SoftDeletes */
        // @phpstan-ignore-next-line because it involves mixins
        return (bool) $model->restore();
    }

    /**
     * Manipulate the AST based on a field definition.
     */
    public function manipulateFieldDefinition(
        DocumentAST &$documentAST,
        FieldDefinitionNode &$fieldDefinition,
        ObjectTypeDefinitionNode &$parentType
    ): void {
        parent::manipulateFieldDefinition($documentAST, $fieldDefinition, $parentType);

        SoftDeletesServiceProvider::assertModelUsesSoftDeletes(
            $this->getModelClass(),
            self::MODEL_NOT_USING_SOFT_DELETES
        );
    }
}
