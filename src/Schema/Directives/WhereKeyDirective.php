<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class WhereKeyDirective extends BaseDirective implements ArgBuilderDirective, FieldBuilderDirective, FieldManipulator
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Add a where clause on the primary key to the Eloquent Model query.
"""
directive @whereKey(
  """
  Provide a value to compare against.
  Exclusively required when this directive is used on a field.
  """
  value: WhereKeyValue
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION | FIELD_DEFINITION

"""
Any constant literal value: https://graphql.github.io/graphql-spec/draft/#sec-Input-Values
"""
scalar WhereKeyValue
GRAPHQL;
    }

    public function handleBuilder($builder, $value): object
    {
        if (! $builder instanceof EloquentBuilder) {
            $notEloquentBuilder = get_class($builder);
            throw new DefinitionException("The {$this->name()} directive only works with queries that use an Eloquent builder, got: {$notEloquentBuilder}.");
        }

        return $builder->whereKey($value);
    }

    public function manipulateFieldDefinition(DocumentAST &$documentAST, FieldDefinitionNode &$fieldDefinition, ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType)
    {
        if (! $this->directiveHasArgument('value')) {
            throw new DefinitionException("Must provide the argument `value` when using {$this->name()} on field `{$parentType->name->value}.{$fieldDefinition->name->value}`.");
        }
    }

    public function handleFieldBuilder(object $builder, $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): object
    {
        return $this->handleBuilder(
            $builder,
            $this->directiveArgValue('value')
        );
    }
}
