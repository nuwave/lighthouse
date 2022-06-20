<?php

namespace Nuwave\Lighthouse\Pagination;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\NonNullTypeNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\TypeNode;
use GraphQL\Language\Parser;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\ModelDirective;

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
            $this->registerConnection($fieldDefinition, $parentType, $paginationType, $defaultCount, $maxCount, $edgeType);
        } elseif ($paginationType->isSimple()) {
            $this->registerSimplePaginator($fieldDefinition, $parentType, $paginationType, $defaultCount, $maxCount);
        } else {
            $this->registerPaginator($fieldDefinition, $parentType, $paginationType, $defaultCount, $maxCount);
        }
    }

    protected function registerConnection(
        FieldDefinitionNode &$fieldDefinition,
        ObjectTypeDefinitionNode &$parentType,
        PaginationType $paginationType,
        ?int $defaultCount = null,
        ?int $maxCount = null,
        ?ObjectTypeDefinitionNode $edgeType = null
    ): void {
        $fieldTypeName = ASTHelper::getUnderlyingTypeName($fieldDefinition);

        if (null !== $edgeType) {
            $connectionEdgeName = $edgeType->name->value;
            $connectionTypeName = "{$connectionEdgeName}Connection";
        } else {
            $connectionEdgeName = "{$fieldTypeName}Edge";
            $connectionTypeName = "{$fieldTypeName}Connection";
        }

        $connectionFieldName = addslashes(ConnectionField::class);

        $connectionType = Parser::objectTypeDefinition(/** @lang GraphQL */ <<<GRAPHQL
            "A paginated list of {$fieldTypeName} edges."
            type {$connectionTypeName} {
                "Pagination information about the list of edges."
                {$paginationType->infoFieldName()}: PageInfo! @field(resolver: "{$connectionFieldName}@pageInfoResolver")

                "A list of {$fieldTypeName} edges."
                edges: [{$connectionEdgeName}!]! @field(resolver: "{$connectionFieldName}@edgeResolver")
            }
GRAPHQL
        );
        $this->addPaginationWrapperType($connectionType);

        $connectionEdge = $edgeType
            ?? $this->documentAST->types[$connectionEdgeName]
            ?? Parser::objectTypeDefinition(/** @lang GraphQL */ <<<GRAPHQL
                "An edge that contains a node of type {$fieldTypeName} and a cursor."
                type {$connectionEdgeName} {
                    "The {$fieldTypeName} node."
                    node: {$fieldTypeName}!

                    "A unique cursor that can be used for pagination."
                    cursor: String!
                }
GRAPHQL
            );
        $this->documentAST->setTypeDefinition($connectionEdge);

        $fieldDefinition->arguments[] = Parser::inputValueDefinition(
            self::countArgument($defaultCount, $maxCount)
        );
        $fieldDefinition->arguments[] = Parser::inputValueDefinition(/** @lang GraphQL */ <<<'GRAPHQL'
"A cursor after which elements are returned."
after: String
GRAPHQL
        );

        $fieldDefinition->type = $this->paginationResultType($connectionTypeName);
        $parentType->fields = ASTHelper::mergeUniqueNodeList($parentType->fields, [$fieldDefinition], true);
    }

    protected function addPaginationWrapperType(ObjectTypeDefinitionNode $objectType): void
    {
        $typeName = $objectType->name->value;

        // Reuse existing types to preserve directives or other modifications made to it
        $existingType = $this->documentAST->types[$typeName] ?? null;
        if (null !== $existingType) {
            if (! $existingType instanceof ObjectTypeDefinitionNode) {
                throw new DefinitionException(
                    "Expected object type for pagination wrapper {$typeName}, found {$objectType->kind} instead."
                );
            }

            $objectType = $existingType;
        }

        if (
            $this->modelClass
            && ! ASTHelper::hasDirective($objectType, ModelDirective::NAME)
        ) {
            $objectType->directives[] = Parser::constDirective(/** @lang GraphQL */ '@model(class: "' . addslashes($this->modelClass) . '")');
        }

        $this->documentAST->setTypeDefinition($objectType);
    }

    protected function registerPaginator(
        FieldDefinitionNode &$fieldDefinition,
        ObjectTypeDefinitionNode &$parentType,
        PaginationType $paginationType,
        ?int $defaultCount = null,
        ?int $maxCount = null
    ): void {
        $fieldTypeName = ASTHelper::getUnderlyingTypeName($fieldDefinition);
        $paginatorTypeName = "{$fieldTypeName}Paginator";
        $paginatorFieldClassName = addslashes(PaginatorField::class);

        $paginatorType = Parser::objectTypeDefinition(/** @lang GraphQL */ <<<GRAPHQL
            "A paginated list of {$fieldTypeName} items."
            type {$paginatorTypeName} {
                "Pagination information about the list of items."
                {$paginationType->infoFieldName()}: PaginatorInfo! @field(resolver: "{$paginatorFieldClassName}@paginatorInfoResolver")

                "A list of {$fieldTypeName} items."
                data: [{$fieldTypeName}!]! @field(resolver: "{$paginatorFieldClassName}@dataResolver")
            }
GRAPHQL
        );
        $this->addPaginationWrapperType($paginatorType);

        $fieldDefinition->arguments[] = Parser::inputValueDefinition(
            self::countArgument($defaultCount, $maxCount)
        );
        $fieldDefinition->arguments[] = Parser::inputValueDefinition(/** @lang GraphQL */ <<<'GRAPHQL'
"The offset from which items are returned."
page: Int
GRAPHQL
        );

        $fieldDefinition->type = $this->paginationResultType($paginatorTypeName);
        $parentType->fields = ASTHelper::mergeUniqueNodeList($parentType->fields, [$fieldDefinition], true);
    }

    protected function registerSimplePaginator(
        FieldDefinitionNode &$fieldDefinition,
        ObjectTypeDefinitionNode &$parentType,
        PaginationType $paginationType,
        ?int $defaultCount = null,
        ?int $maxCount = null
    ): void {
        $fieldTypeName = ASTHelper::getUnderlyingTypeName($fieldDefinition);
        $paginatorTypeName = "{$fieldTypeName}SimplePaginator";
        $paginatorFieldClassName = addslashes(SimplePaginatorField::class);

        $paginatorType = Parser::objectTypeDefinition(/** @lang GraphQL */ <<<GRAPHQL
            "A paginated list of {$fieldTypeName} items."
            type {$paginatorTypeName} {
                "Pagination information about the list of items."
                {$paginationType->infoFieldName()}: SimplePaginatorInfo! @field(resolver: "{$paginatorFieldClassName}@paginatorInfoResolver")

                "A list of {$fieldTypeName} items."
                data: [{$fieldTypeName}!]! @field(resolver: "{$paginatorFieldClassName}@dataResolver")
            }
GRAPHQL
        );
        $this->addPaginationWrapperType($paginatorType);

        $fieldDefinition->arguments[] = Parser::inputValueDefinition(
            self::countArgument($defaultCount, $maxCount)
        );
        $fieldDefinition->arguments[] = Parser::inputValueDefinition(/** @lang GraphQL */ <<<'GRAPHQL'
"The offset from which items are returned."
page: Int
GRAPHQL
        );

        $fieldDefinition->type = $this->paginationResultType($paginatorTypeName);
        $parentType->fields = ASTHelper::mergeUniqueNodeList($parentType->fields, [$fieldDefinition], true);
    }

    /**
     * Build the count argument definition string, considering default and max values.
     */
    protected static function countArgument(?int $defaultCount = null, ?int $maxCount = null): string
    {
        $description = '"Limits number of fetched items.';
        if ($maxCount) {
            $description .= " Maximum allowed value: {$maxCount}.";
        }
        $description .= "\"\n";

        // TODO always add ! in v6
        $definition = 'first: Int'
            . ($defaultCount
                ? " =  {$defaultCount}"
                : '!');

        return $description . $definition;
    }

    /**
     * @return \GraphQL\Language\AST\NamedTypeNode|\GraphQL\Language\AST\NonNullTypeNode
     */
    protected function paginationResultType(string $typeName): TypeNode
    {
        $config = Container::getInstance()->make(ConfigRepository::class);
        assert($config instanceof ConfigRepository);
        $nonNull = $config->get('lighthouse.non_null_pagination_results')
            ? '!'
            : '';

        $typeNode = Parser::typeReference(/** @lang GraphQL */ "{$typeName}{$nonNull}");
        assert(
            $typeNode instanceof NamedTypeNode || $typeNode instanceof NonNullTypeNode,
            'We do not wrap the typename in [], so this will never be a ListOfTypeNode.'
        );

        return $typeNode;
    }
}
