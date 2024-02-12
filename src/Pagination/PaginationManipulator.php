<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Pagination;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\NonNullTypeNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use Nuwave\Lighthouse\CacheControl\CacheControlServiceProvider;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Federation\FederationHelper;
use Nuwave\Lighthouse\Federation\FederationServiceProvider;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\ModelDirective;

class PaginationManipulator
{
    /**
     * The class name of the model that is returned from the field.
     *
     * Might not be present if we are creating a paginated field
     * for a relation, as the model is not required for resolving
     * that directive and the user may choose a different type.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>|null
     */
    protected ?string $modelClass = null;

    public function __construct(
        protected DocumentAST $documentAST,
    ) {}

    /**
     * Set the model class to use for code generation.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>|null  $modelClass
     */
    public function setModelClass(?string $modelClass): self
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
        ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType,
        ?int $defaultCount = null,
        ?int $maxCount = null,
        ?ObjectTypeDefinitionNode $edgeType = null,
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
        ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType,
        PaginationType $paginationType,
        ?int $defaultCount = null,
        ?int $maxCount = null,
        ?ObjectTypeDefinitionNode $edgeType = null,
    ): void {
        $pageInfoNode = $this->pageInfo();
        if (! isset($this->documentAST->types[$pageInfoNode->getName()->value])) {
            $this->documentAST->setTypeDefinition($pageInfoNode);
        }

        $fieldTypeName = ASTHelper::getUnderlyingTypeName($fieldDefinition);

        if ($edgeType !== null) {
            $connectionEdgeName = $edgeType->name->value;
            $connectionTypeName = "{$connectionEdgeName}Connection";
        } else {
            $connectionEdgeName = "{$fieldTypeName}Edge";
            $connectionTypeName = "{$fieldTypeName}Connection";
        }

        $connectionFieldClass = addslashes(ConnectionField::class);
        $connectionType = Parser::objectTypeDefinition(/** @lang GraphQL */ <<<GRAPHQL
            "A paginated list of {$fieldTypeName} edges."
            type {$connectionTypeName} {
                "Pagination information about the list of edges."
                {$paginationType->infoFieldName()}: PageInfo! @field(resolver: "{$connectionFieldClass}@pageInfoResolver") {$this->maybeInheritCacheControlDirective()}

                "A list of {$fieldTypeName} edges."
                edges: [{$connectionEdgeName}!]! @field(resolver: "{$connectionFieldClass}@edgeResolver") {$this->maybeInheritCacheControlDirective()}
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
            self::countArgument($defaultCount, $maxCount),
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
        if ($existingType !== null) {
            if (! $existingType instanceof ObjectTypeDefinitionNode) {
                throw new DefinitionException("Expected object type for pagination wrapper {$typeName}, found {$objectType->kind} instead.");
            }

            $objectType = $existingType;
        }

        if (
            isset($this->modelClass)
            && ! ASTHelper::hasDirective($objectType, ModelDirective::NAME)
        ) {
            $modelClassEscaped = addslashes($this->modelClass);
            $objectType->directives[] = Parser::constDirective(/** @lang GraphQL */ <<<GRAPHQL
                @model(class: "{$modelClassEscaped}")
            GRAPHQL);
        }

        $this->documentAST->setTypeDefinition($objectType);
    }

    protected function registerPaginator(
        FieldDefinitionNode &$fieldDefinition,
        ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType,
        PaginationType $paginationType,
        ?int $defaultCount = null,
        ?int $maxCount = null,
    ): void {
        $paginatorInfoNode = $this->paginatorInfo();
        if (! isset($this->documentAST->types[$paginatorInfoNode->getName()->value])) {
            $this->documentAST->setTypeDefinition($paginatorInfoNode);
        }

        $fieldTypeName = ASTHelper::getUnderlyingTypeName($fieldDefinition);
        $paginatorTypeName = "{$fieldTypeName}Paginator";
        $paginatorFieldClassName = addslashes(PaginatorField::class);

        $paginatorType = Parser::objectTypeDefinition(/** @lang GraphQL */ <<<GRAPHQL
            "A paginated list of {$fieldTypeName} items."
            type {$paginatorTypeName} {
                "Pagination information about the list of items."
                {$paginationType->infoFieldName()}: PaginatorInfo! @field(resolver: "{$paginatorFieldClassName}@paginatorInfoResolver") {$this->maybeInheritCacheControlDirective()}

                "A list of {$fieldTypeName} items."
                data: [{$fieldTypeName}!]! @field(resolver: "{$paginatorFieldClassName}@dataResolver") {$this->maybeInheritCacheControlDirective()}
            }
        GRAPHQL);
        $this->addPaginationWrapperType($paginatorType);

        $fieldDefinition->arguments[] = Parser::inputValueDefinition(
            self::countArgument($defaultCount, $maxCount),
        );
        $fieldDefinition->arguments[] = Parser::inputValueDefinition(/** @lang GraphQL */ <<<'GRAPHQL'
            "The offset from which items are returned."
            page: Int
        GRAPHQL);

        $fieldDefinition->type = $this->paginationResultType($paginatorTypeName);
        $parentType->fields = ASTHelper::mergeUniqueNodeList($parentType->fields, [$fieldDefinition], true);
    }

    protected function registerSimplePaginator(
        FieldDefinitionNode &$fieldDefinition,
        ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType,
        PaginationType $paginationType,
        ?int $defaultCount = null,
        ?int $maxCount = null,
    ): void {
        $simplePaginatorInfoNode = $this->simplePaginatorInfo();
        if (! isset($this->documentAST->types[$simplePaginatorInfoNode->getName()->value])) {
            $this->documentAST->setTypeDefinition($simplePaginatorInfoNode);
        }

        $fieldTypeName = ASTHelper::getUnderlyingTypeName($fieldDefinition);
        $paginatorTypeName = "{$fieldTypeName}SimplePaginator";
        $paginatorFieldClassName = addslashes(SimplePaginatorField::class);

        $paginatorType = Parser::objectTypeDefinition(/** @lang GraphQL */ <<<GRAPHQL
            "A paginated list of {$fieldTypeName} items."
            type {$paginatorTypeName} {
                "Pagination information about the list of items."
                {$paginationType->infoFieldName()}: SimplePaginatorInfo! @field(resolver: "{$paginatorFieldClassName}@paginatorInfoResolver") {$this->maybeInheritCacheControlDirective()}

                "A list of {$fieldTypeName} items."
                data: [{$fieldTypeName}!]! @field(resolver: "{$paginatorFieldClassName}@dataResolver") {$this->maybeInheritCacheControlDirective()}
            }
        GRAPHQL);
        $this->addPaginationWrapperType($paginatorType);

        $fieldDefinition->arguments[] = Parser::inputValueDefinition(
            self::countArgument($defaultCount, $maxCount),
        );
        $fieldDefinition->arguments[] = Parser::inputValueDefinition(/** @lang GraphQL */ <<<'GRAPHQL'
            "The offset from which items are returned."
            page: Int
        GRAPHQL);

        $fieldDefinition->type = $this->paginationResultType($paginatorTypeName);
        $parentType->fields = ASTHelper::mergeUniqueNodeList($parentType->fields, [$fieldDefinition], true);
    }

    /** Build the count argument definition string, considering default and max values. */
    protected static function countArgument(?int $defaultCount = null, ?int $maxCount = null): string
    {
        $description = '"Limits number of fetched items.';
        if ($maxCount) {
            $description .= " Maximum allowed value: {$maxCount}.";
        }

        $description .= "\"\n";

        $definition = 'first: Int!';
        if ($defaultCount) {
            $definition .= " =  {$defaultCount}";
        }

        return $description . $definition;
    }

    protected function paginationResultType(string $typeName): NonNullTypeNode
    {
        $typeNode = Parser::typeReference(/** @lang GraphQL */ "{$typeName}!");
        assert(
            $typeNode instanceof NonNullTypeNode,
            'We do not wrap the typename in [], so this will never be a ListOfTypeNode.',
        );

        return $typeNode;
    }

    protected function paginatorInfo(): ObjectTypeDefinitionNode
    {
        return Parser::objectTypeDefinition(/** @lang GraphQL */ <<<GRAPHQL
            "Information about pagination using a fully featured paginator."
            type PaginatorInfo {$this->maybeAddShareableDirective()} {
              "Number of items in the current page."
              count: Int!

              "Index of the current page."
              currentPage: Int!

              "Index of the first item in the current page."
              firstItem: Int

              "Are there more pages after this one?"
              hasMorePages: Boolean!

              "Index of the last item in the current page."
              lastItem: Int

              "Index of the last available page."
              lastPage: Int!

              "Number of items per page."
              perPage: Int!

              "Number of total available items."
              total: Int!
            }
        GRAPHQL);
    }

    protected function simplePaginatorInfo(): ObjectTypeDefinitionNode
    {
        return Parser::objectTypeDefinition(/** @lang GraphQL */ <<<GRAPHQL
            "Information about pagination using a simple paginator."
            type SimplePaginatorInfo {$this->maybeAddShareableDirective()} {
              "Number of items in the current page."
              count: Int!

              "Index of the current page."
              currentPage: Int!

              "Index of the first item in the current page."
              firstItem: Int

              "Index of the last item in the current page."
              lastItem: Int

              "Number of items per page."
              perPage: Int!

              "Are there more pages after this one?"
              hasMorePages: Boolean!
            }
            GRAPHQL);
    }

    protected function pageInfo(): ObjectTypeDefinitionNode
    {
        return Parser::objectTypeDefinition(/** @lang GraphQL */ <<<GRAPHQL
            "Information about pagination using a Relay style cursor connection."
            type PageInfo {$this->maybeAddShareableDirective()} {
              "When paginating forwards, are there more items?"
              hasNextPage: Boolean!

              "When paginating backwards, are there more items?"
              hasPreviousPage: Boolean!

              "The cursor to continue paginating backwards."
              startCursor: String

              "The cursor to continue paginating forwards."
              endCursor: String

              "Total number of nodes in the paginated connection."
              total: Int!

              "Number of nodes in the current page."
              count: Int!

              "Index of the current page."
              currentPage: Int!

              "Index of the last available page."
              lastPage: Int!
            }
            GRAPHQL);
    }

    /**
     * If cache control is used, inherit the max age set in the parent field.
     *
     * Pagination adds the nested fields `paginatorInfo` and `data`.
     * Cache control identifies this as a new entity, but it should actually not affect the HTTP cache header values.
     * Therefore, the @cacheControl directive is applied to inherit whatever max age the parent field set.
     */
    private function maybeInheritCacheControlDirective(): string
    {
        // Not using Illuminate\Container\Container::getInstance() here as it causes PHPStan issues
        if (app()->providerIsLoaded(CacheControlServiceProvider::class)) {
            return /** @lang GraphQL */ '@cacheControl(inheritMaxAge: true)';
        }

        return '';
    }

    /** If federation v2 is used, add the @shareable directive to the pagination generic types. */
    private function maybeAddShareableDirective(): string
    {
        if (app()->providerIsLoaded(FederationServiceProvider::class) && FederationHelper::isUsingFederationV2($this->documentAST)) {
            return /** @lang GraphQL */ '@shareable';
        }

        return '';
    }
}
