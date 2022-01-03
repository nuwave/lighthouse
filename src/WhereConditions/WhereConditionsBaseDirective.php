<?php

namespace Nuwave\Lighthouse\WhereConditions;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Traits\GeneratesColumnsEnum;
use Nuwave\Lighthouse\Support\Traits\GeneratesRelationsEnum;

abstract class WhereConditionsBaseDirective extends BaseDirective implements ArgBuilderDirective, ArgManipulator
{
    use GeneratesColumnsEnum;
    use GeneratesRelationsEnum;

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder  the builder used to resolve the field
     * @param  array<string, mixed>  $value  the client given value of the argument
     */
    protected function handle($builder, array $value): void
    {
        $handler = $this->directiveHasArgument('handler')
            ? $this->getResolverFromArgument('handler')
            : app(WhereConditionsHandler::class);

        $handler($builder, $value);
    }

    public function manipulateArgDefinition(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$argDefinition,
        FieldDefinitionNode &$parentField,
        ObjectTypeDefinitionNode &$parentType
    ): void {
        $hasAllowedColumns = $this->hasAllowedColumns();
        $hasAllowedRelations = $this->hasAllowedRelations();

        if ($hasAllowedColumns || $hasAllowedRelations) {
            $qualifiedWhereConditionsName = ASTHelper::qualifiedArgType($argDefinition, $parentField, $parentType) . $this->generatedInputSuffix();
            $argDefinition->type = Parser::namedType($qualifiedWhereConditionsName);

            $documentAST->setTypeDefinition(
                WhereConditionsServiceProvider::createWhereConditionsInputType(
                    $qualifiedWhereConditionsName,
                    "Dynamic WHERE conditions for Query.{$parentField->name->value}.{$argDefinition->name->value}.",
                    $hasAllowedColumns
                        ? $this->generateColumnsEnum($documentAST, $argDefinition, $parentField, $parentType)
                        : 'String'
                )
            );

            $documentAST->setTypeDefinition(
                WhereConditionsServiceProvider::createHasConditionsInputType(
                    $qualifiedWhereConditionsName,
                    "Dynamic HAS conditions for Query.{$parentField->name->value}.{$argDefinition->name->value}.",
                    $hasAllowedRelations
                        ? $this->generateRelationsEnum($documentAST, $argDefinition, $parentField, $parentType)
                        : 'String'
                )
            );
        } else {
            $argDefinition->type = Parser::namedType(WhereConditionsServiceProvider::DEFAULT_WHERE_CONDITIONS);
        }
    }

    /**
     * Get the suffix that will be added to generated input types.
     */
    abstract protected function generatedInputSuffix(): string;
}
