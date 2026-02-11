<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Illuminate\Database\Eloquent\Relations\Relation;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\Arguments\SaveModel;
use Nuwave\Lighthouse\Execution\Arguments\UpsertModel;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\InputFieldManipulator;

class UpsertDirective extends OneModelMutationDirective implements ArgManipulator, FieldManipulator, InputFieldManipulator
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Create or update an Eloquent model with the given arguments.
"""
directive @upsert(
  """
  Specify the class name of the model to use.
  This is only needed when the default model detection does not work.
  """
  model: String

  """
  Specify the columns by which to upsert the model.
  Optional, by default `id` or the primary key of the model are used.
  """
  identifyingColumns: [String!]

  """
  Specify the name of the relation on the parent model.
  This is only needed when using this directive as a nested arg
  resolver and if the name of the relation is not the arg name.
  """
  relation: String
) on FIELD_DEFINITION | ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    protected function makeExecutionFunction(?Relation $parentRelation = null): callable
    {
        return new UpsertModel(
            new SaveModel($parentRelation),
            $this->directiveArgValue('identifyingColumns'),
            $parentRelation,
        );
    }

    public function manipulateArgDefinition(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$argDefinition,
        FieldDefinitionNode &$parentField,
        ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType,
    ): void {
        $this->ensureNonEmptyIdentifyingColumns("{$parentType->name->value}.{$parentField->name->value}:{$argDefinition->name->value}");
    }

    public function manipulateFieldDefinition(
        DocumentAST &$documentAST,
        FieldDefinitionNode &$fieldDefinition,
        ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType,
    ): void {
        $this->ensureNonEmptyIdentifyingColumns("{$parentType->name->value}.{$fieldDefinition->name->value}");
    }

    public function manipulateInputFieldDefinition(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$inputField,
        InputObjectTypeDefinitionNode &$parentInput,
    ): void {
        $this->ensureNonEmptyIdentifyingColumns("{$parentInput->name->value}.{$inputField->name->value}");
    }

    protected function ensureNonEmptyIdentifyingColumns(string $location): void
    {
        $identifyingColumns = $this->directiveArgValue('identifyingColumns');

        if (! is_array($identifyingColumns) || $identifyingColumns !== []) {
            return;
        }

        throw new DefinitionException("Must specify non-empty list of columns in `identifyingColumns` argument of `@{$this->name()}` directive on `{$location}`.");
    }
}
