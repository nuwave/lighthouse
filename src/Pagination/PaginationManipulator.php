<?php

namespace Nuwave\Lighthouse\Pagination;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;

class PaginationManipulator
{
    /**
     * @var \Nuwave\Lighthouse\Schema\AST\DocumentAST
     */
    protected $documentAST;

    /**
     * The class name of the model that is returned from the field.
     *
     * Might not be present if we are creating a paginated field
     * for a relation, as the model is not required for resolving
     * that directive and the user may choose a different type.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>|null
     */
    protected $modelClass;

    public function __construct(DocumentAST $documentAST)
    {
        $this->documentAST = $documentAST;
    }

    /**
     * Set the model class to use for code generation.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
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
     */
    protected function registerConnection(
        FieldDefinitionNode &$fieldDefinition,
        ObjectTypeDefinitionNode &$parentType,
        ?int $defaultCount = null,
        ?int $maxCount = null,
        ?ObjectTypeDefinitionNode $edgeType = null
    ): void {
        $fieldTypeName = ASTHelper::getUnderlyingTypeName($fieldDefinition);

        if ($edgeType !== null) {
            $connectionEdgeName = $edgeType->name->value;
            $connectionTypeName = "{$connectionEdgeName}Connection";
        } else {
            $connectionEdgeName = "{$fieldTypeName}Edge";
            $connectionTypeName = "{$fieldTypeName}Connection";
        }

        $connectionFieldName = addslashes(ConnectionField::class);

        $connectionType = Parser::objectTypeDefinition(/** @lang GraphQL */ <<<GRAPHQL
            "A paginated list of $fieldTypeName edges."
            type $connectionTypeName {
                "Pagination information about the list of edges."
                pageInfo: PageInfo! @field(resolver: "{$connectionFieldName}@pageInfoResolver")

                "A list of $fieldTypeName edges."
                edges: [$connectionEdgeName] @field(resolver: "{$connectionFieldName}@edgeResolver")
            }
GRAPHQL
        );
        $this->addPaginationWrapperType($connectionType);

        $connectionEdge = $edgeType
            ?? $this->documentAST->types[$connectionEdgeName]
            ?? Parser::objectTypeDefinition(/** @lang GraphQL */ <<<GRAPHQL
                "An edge that contains a node of type $fieldTypeName and a cursor."
                type $connectionEdgeName {
                    "The $fieldTypeName node."
                    node: $fieldTypeName

                    "A unique cursor that can be used for pagination."
                    cursor: String!
                }
GRAPHQL
            );
        $this->documentAST->setTypeDefinition($connectionEdge);

        $fieldDefinition->arguments [] = Parser::inputValueDefinition(
            self::countArgument($defaultCount, $maxCount)
        );
        $fieldDefinition->arguments [] = Parser::inputValueDefinition(/** @lang GraphQL */ <<<'GRAPHQL'
"A cursor after which elements are returned."
after: String
GRAPHQL
        );

        $fieldDefinition->type = Parser::namedType($connectionTypeName);
        $parentType->fields [] = $fieldDefinition;
    }

    /**
     * Add the wrapping type for paginated results.
     *
     * This merges preexisting definitions to preserve maximum information.
     */
    protected function addPaginationWrapperType(ObjectTypeDefinitionNode $objectType): void
    {
        // If the type already exists, we use that instead
        if (isset($this->documentAST->types[$objectType->name->value])) {
            $objectType = $this->documentAST->types[$objectType->name->value];

            if (! $objectType instanceof ObjectTypeDefinitionNode) {
                throw new DefinitionException(
                    'Expected object type for pagination wrapper '.$objectType->name->value
                    .', found '.$objectType->kind.' instead.'
                );
            }
        }

        if ($this->modelClass) {
            $objectType->directives [] = Parser::constDirective('@model(class: "'.addslashes($this->modelClass).'")');
        }

        $this->documentAST->setTypeDefinition($objectType);
    }

    /**
     * Register paginator with schema.
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

        $paginatorType = Parser::objectTypeDefinition(/** @lang GraphQL */ <<<GRAPHQL
            "A paginated list of $fieldTypeName items."
            type $paginatorTypeName {
                "Pagination information about the list of items."
                paginatorInfo: PaginatorInfo! @field(resolver: "{$paginatorFieldClassName}@paginatorInfoResolver")

                "A list of $fieldTypeName items."
                data: [$fieldTypeName!]! @field(resolver: "{$paginatorFieldClassName}@dataResolver")
            }
GRAPHQL
        );
        $this->addPaginationWrapperType($paginatorType);

        $fieldDefinition->arguments [] = Parser::inputValueDefinition(
            self::countArgument($defaultCount, $maxCount)
        );
        $fieldDefinition->arguments [] = Parser::inputValueDefinition("\"The offset from which elements are returned.\"\npage: Int");

        $fieldDefinition->type = Parser::namedType($paginatorTypeName);
        $parentType->fields [] = $fieldDefinition;
    }

    /**
     * Build the count argument definition string, considering default and max values.
     */
    protected static function countArgument(?int $defaultCount = null, ?int $maxCount = null): string
    {
        $description = '"Limits number of fetched elements.';
        if ($maxCount) {
            $description .= ' Maximum allowed value: '.$maxCount.'.';
        }
        $description .= "\"\n";

        $definition = 'first: Int'
            .($defaultCount
                ? ' = '.$defaultCount
                : '!'
            );

        return $description.$definition;
    }
}
