<?php

namespace Nuwave\Lighthouse\Pagination;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\AST\PartialParser;

class PaginationManipulator
{
    /**
     * @var DocumentAST
     */
    protected $documentAST;

    /**
     * The class name of the model that is returned from the field.
     *
     * Might not be present if we are creating a paginated field
     * for a relation, as the model is not required for resolving
     * that directive and the user may choose a different type.
     *
     * @var string|null
     */
    protected $modelClass;

    public function __construct(DocumentAST $documentAST)
    {
        $this->documentAST = $documentAST;
    }

    /**
     * Set the model class to use for code generation.
     *
     * @param  string  $modelClass
     * @return $this
     */
    public function setModelClass(string $modelClass): self
    {
        $this->modelClass = $modelClass;

        return $this;
    }

    /**
     * Transform the definition for a field to a field with pagination.
     *
     * This makes either an offset-based Paginator or a cursor-based Connection.
     * The types in between are automatically generated and applied to the schema.
     *
     * @param  \Nuwave\Lighthouse\Pagination\PaginationType  $paginationType
     * @param  \GraphQL\Language\AST\FieldDefinitionNode  $fieldDefinition
     * @param  \GraphQL\Language\AST\ObjectTypeDefinitionNode  $parentType
     * @param  int|null  $defaultCount
     * @param  int|null  $maxCount
     * @param  \GraphQL\Language\AST\ObjectTypeDefinitionNode|null  $edgeType
     * @return void
     */
    public function transformToPaginatedField(
        PaginationType $paginationType,
        FieldDefinitionNode &$fieldDefinition,
        ObjectTypeDefinitionNode &$parentType,
        ?int $defaultCount = null,
        ?int $maxCount = null,
        ?ObjectTypeDefinitionNode $edgeType = null
    ): void {
        if ($paginationType->isConnection()) {
            $this->registerConnection($fieldDefinition, $parentType, $defaultCount, $maxCount, $edgeType);
        } else {
            $this->registerPaginator($fieldDefinition, $parentType, $defaultCount, $maxCount);
        }
    }

    /**
     * Register connection with schema.
     *
     * @param  \GraphQL\Language\AST\FieldDefinitionNode  $fieldDefinition
     * @param  \GraphQL\Language\AST\ObjectTypeDefinitionNode  $parentType
     * @param  int|null  $defaultCount
     * @param  int|null  $maxCount
     * @param  \GraphQL\Language\AST\ObjectTypeDefinitionNode|null  $edgeType
     * @return  void
     */
    protected function registerConnection(
        FieldDefinitionNode &$fieldDefinition,
        ObjectTypeDefinitionNode &$parentType,
        ?int $defaultCount = null,
        ?int $maxCount = null,
        ?ObjectTypeDefinitionNode $edgeType = null
    ): void {
        $fieldTypeName = ASTHelper::getUnderlyingTypeName($fieldDefinition);

        if ($edgeType) {
            $connectionEdgeName = $edgeType->name->value;
            $connectionTypeName = "{$connectionEdgeName}Connection";
        } else {
            $connectionEdgeName = "{$fieldTypeName}Edge";
            $connectionTypeName = "{$fieldTypeName}Connection";
        }

        $connectionFieldName = addslashes(ConnectionField::class);

        $connectionType = PartialParser::objectTypeDefinition("
            type $connectionTypeName {$this->modelClassDirective()} {
                pageInfo: PageInfo! @field(resolver: \"{$connectionFieldName}@pageInfoResolver\")
                edges: [$connectionEdgeName] @field(resolver: \"{$connectionFieldName}@edgeResolver\")
            }
        ");

        $connectionEdge = $edgeType
            ?? $this->documentAST->types[$connectionEdgeName]
            ?? PartialParser::objectTypeDefinition("
                type $connectionEdgeName {
                    node: $fieldTypeName
                    cursor: String!
                }
            ");

        $inputValueDefinitions = [
            self::countArgument('first', $defaultCount, $maxCount),
            "\"A cursor after which elements are returned.\"\nafter: String",
        ];

        $connectionArguments = PartialParser::inputValueDefinitions($inputValueDefinitions);

        $fieldDefinition->arguments = ASTHelper::mergeNodeList($fieldDefinition->arguments, $connectionArguments);
        $fieldDefinition->type = PartialParser::namedType($connectionTypeName);
        $parentType->fields = ASTHelper::mergeNodeList($parentType->fields, [$fieldDefinition]);

        $this->documentAST->setTypeDefinition($connectionType);
        $this->documentAST->setTypeDefinition($connectionEdge);
    }

    /**
     * Register paginator with schema.
     *
     * @param  \GraphQL\Language\AST\FieldDefinitionNode  $fieldDefinition
     * @param  \GraphQL\Language\AST\ObjectTypeDefinitionNode  $parentType
     * @param  int|null  $defaultCount
     * @param  int|null  $maxCount
     * @return void
     */
    protected function registerPaginator(
        FieldDefinitionNode &$fieldDefinition,
        ObjectTypeDefinitionNode &$parentType,
        ?int $defaultCount = null,
        ?int $maxCount = null
    ): void {
        $fieldTypeName = ASTHelper::getUnderlyingTypeName($fieldDefinition);
        $paginatorTypeName = "{$fieldTypeName}Paginator";
        $paginatorFieldClassName = addslashes(PaginatorField::class);

        $paginatorType = PartialParser::objectTypeDefinition("
            type $paginatorTypeName {$this->modelClassDirective()} {
                paginatorInfo: PaginatorInfo! @field(resolver: \"{$paginatorFieldClassName}@paginatorInfoResolver\")
                data: [$fieldTypeName!]! @field(resolver: \"{$paginatorFieldClassName}@dataResolver\")
            }
        ");

        $inputValueDefinitions = [
            self::countArgument(config('lighthouse.pagination_amount_argument'), $defaultCount, $maxCount),
            "\"The offset from which elements are returned.\"\npage: Int",
        ];

        $paginationArguments = PartialParser::inputValueDefinitions($inputValueDefinitions);

        $fieldDefinition->arguments = ASTHelper::mergeNodeList($fieldDefinition->arguments, $paginationArguments);
        $fieldDefinition->type = PartialParser::namedType($paginatorTypeName);
        $parentType->fields = ASTHelper::mergeNodeList($parentType->fields, [$fieldDefinition]);

        $this->documentAST->setTypeDefinition($paginatorType);
    }

    /**
     * Build the count argument definition string, considering default and max values.
     *
     * @param  string  $argumentName
     * @param  int|null  $defaultCount
     * @param  int|null  $maxCount
     * @return string
     */
    protected static function countArgument(string $argumentName, ?int $defaultCount = null, ?int $maxCount = null): string
    {
        $description = '"Limits number of fetched elements.';
        if ($maxCount) {
            $description .= ' Maximum allowed value: '.$maxCount.'.';
        }
        $description .= "\"\n";

        $definition = $argumentName.': Int'
            .($defaultCount
                ? ' = '.$defaultCount
                : '!'
            );

        return $description.$definition;
    }

    /**
     * Get the definition for the @modelClass directive if needed.
     *
     * This will be empty when not applicable.
     *
     * @return string
     */
    protected function modelClassDirective(): string
    {
        return $this->modelClass
            ? '@modelClass(class: "'.addslashes($this->modelClass).'")'
            : '';
    }
}
