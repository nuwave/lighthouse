<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;

class EqDirective extends BaseDirective implements ArgBuilderDirective, FieldBuilderDirective, FieldManipulator
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Use the client given value to add an equal conditional to a database query.
"""
directive @eq(
  """
  Specify the database column to compare.
  Required if the directive is:
  - used on an argument and the database column has a different name
  - used on a field
  """
  key: String

  """
  Provide a value to compare against.
  Only required when this directive is used on a field.
  """
  value: Mixed
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    /**
     * Apply a "WHERE = $value" clause.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function handleBuilder($builder, $value): object
    {
        return $builder->where(
            $this->directiveArgValue('key', $this->nodeName()),
            $value
        );
    }

    public function manipulateFieldDefinition(DocumentAST &$documentAST, FieldDefinitionNode &$fieldDefinition, ObjectTypeDefinitionNode &$parentType)
    {
        if (! $this->directiveHasArgument('value')) {
            throw new DefinitionException("Must provide the argument `value` when using {$this->name()} on field `{$parentType->name->value}.{$fieldDefinition->name->value}`.");
        }

        if (! $this->directiveHasArgument('key')) {
            throw new DefinitionException("Must provide the argument `key` when using {$this->name()} on field `{$parentType->name->value}.{$fieldDefinition->name->value}`.");
        }
    }

    public function handleFieldBuilder(object $builder): object
    {
        return $this->handleBuilder(
            $builder,
            $this->directiveArgValue('value')
        );
    }
}
