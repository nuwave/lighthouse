<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\WhereConditions;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Traits\GeneratesColumnsEnum;

abstract class WhereConditionsBaseDirective extends BaseDirective implements ArgBuilderDirective, ArgManipulator
{
    use GeneratesColumnsEnum;

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>|\Illuminate\Database\Eloquent\Relations\Relation<\Illuminate\Database\Eloquent\Model>  $builder  the builder used to resolve the field
     * @param  array<string, mixed>  $value  the client given value of the argument
     */
    protected function handle(QueryBuilder|EloquentBuilder|Relation $builder, array $value): void
    {
        $handler = $this->directiveHasArgument('handler')
            ? $this->getResolverFromArgument('handler')
            : Container::getInstance()->make(WhereConditionsHandler::class);

        $handler($builder, $value);
    }

    public function manipulateArgDefinition(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$argDefinition,
        FieldDefinitionNode &$parentField,
        ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType,
    ): void {
        $this->validateMutuallyExclusiveArguments(['columns', 'columnsEnum']);

        if ($this->hasAllowedColumns()) {
            $restrictedWhereConditionsName = ASTHelper::qualifiedArgType($argDefinition, $parentField, $parentType) . $this->generatedInputSuffix();
            $argDefinition->type = Parser::namedType($restrictedWhereConditionsName);
            $allowedColumnsEnumName = $this->generateColumnsEnum($documentAST, $argDefinition, $parentField, $parentType);

            $documentAST
                ->setTypeDefinition(
                    WhereConditionsServiceProvider::createWhereConditionsInputType(
                        $restrictedWhereConditionsName,
                        "Dynamic WHERE conditions for the `{$argDefinition->name->value}` argument of the query `{$parentField->name->value}`.",
                        $allowedColumnsEnumName,
                    ),
                )
                ->setTypeDefinition(
                    WhereConditionsServiceProvider::createHasConditionsInputType(
                        $restrictedWhereConditionsName,
                        "Dynamic HAS conditions for WHERE conditions for the `{$argDefinition->name->value}` argument of the query `{$parentField->name->value}`.",
                    ),
                );
        } else {
            $argDefinition->type = Parser::namedType(WhereConditionsServiceProvider::DEFAULT_WHERE_CONDITIONS);
        }
    }

    /** Get the suffix that will be added to generated input types. */
    abstract protected function generatedInputSuffix(): string;
}
