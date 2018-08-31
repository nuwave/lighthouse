<?php

namespace Nuwave\Lighthouse\Schema\AST;

use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\TypeExtensionNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Contracts\NodeManipulator;
use Nuwave\Lighthouse\Schema\Extensions\GraphQLExtension;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;

class ASTBuilder
{
    /**
     * @param string $schema
     *
     * @return DocumentAST
     */
    public static function generate(string $schema): DocumentAST
    {
        $document = DocumentAST::fromSource($schema);

        // Node manipulators may be defined on type extensions
        $document = self::applyNodeManipulators($document);
        // After they have been applied, we can safely merge them
        $document = self::mergeTypeExtensions($document);

        $document = self::applyFieldManipulators($document);
        $document = self::applyArgManipulators($document);

        $document = self::addNodeSupport($document);
        $document = self::addPaginationInfoTypes($document);
        $document = self::applyExtensions($document);

        return $document;
    }

    /**
     * @param DocumentAST $document
     *
     * @return DocumentAST
     */
    protected static function applyNodeManipulators(DocumentAST $document): DocumentAST
    {
        $originalDocument = $document;

        return $document->typeExtensionDefinitions()
            // This is just temporarily merged together
            ->concat($document->typeDefinitions())
            ->reduce(function (DocumentAST $document, Node $node) use (
                $originalDocument
            ) {
                $nodeManipulators = graphql()->directives()->nodeManipulators($node);

                return $nodeManipulators->reduce(function (DocumentAST $document, NodeManipulator $nodeManipulator) use (
                    $originalDocument,
                    $node
                ) {
                    return $nodeManipulator->manipulateSchema($node, $document, $originalDocument);
                }, $document);
            }, $document);
    }
  
    /**
     * @param DocumentAST $document
     *
     * @return DocumentAST
     */
    protected static function mergeTypeExtensions(DocumentAST $document): DocumentAST
    {
        $document->objectTypeDefinitions()->each(function (ObjectTypeDefinitionNode $objectType) use ($document) {
            $name = $objectType->name->value;

            $document->typeExtensionDefinitions($name)->reduce(function (
                ObjectTypeDefinitionNode $relatedObjectType,
                TypeExtensionNode $typeExtension
            ) {
                /** @var NodeList $fields */
                $fields = $relatedObjectType->fields;
                $relatedObjectType->fields = $fields->merge($typeExtension->fields);

                return $relatedObjectType;
            }, $objectType);

            // Modify the original document by overwriting the definition with the merged one
            $document->setDefinition($objectType);
        });

        return $document;
    }

    /**
     * @param DocumentAST $document
     *
     * @return DocumentAST
     */
    protected static function applyFieldManipulators(DocumentAST $document): DocumentAST
    {
        $originalDocument = $document;

        return $document->objectTypeDefinitions()->reduce(function (
            DocumentAST $document,
            ObjectTypeDefinitionNode $objectType
        ) use ($originalDocument) {
            return collect($objectType->fields)->reduce(function (
                DocumentAST $document,
                FieldDefinitionNode $fieldDefinition
            ) use ($objectType, $originalDocument) {
                $fieldManipulators = graphql()->directives()->fieldManipulators($fieldDefinition);

                return $fieldManipulators->reduce(function (
                    DocumentAST $document,
                    FieldManipulator $fieldManipulator
                ) use ($fieldDefinition, $objectType, $originalDocument) {
                    return $fieldManipulator->manipulateSchema($fieldDefinition, $objectType, $document,
                        $originalDocument);
                }, $document);
            }, $document);
        }, $document);
    }

    /**
     * @param DocumentAST $document
     *
     * @return DocumentAST
     */
    protected static function applyArgManipulators(DocumentAST $document): DocumentAST
    {
        $originalDocument = $document;

        return $document->objectTypeDefinitions()->reduce(
            function (DocumentAST $document, ObjectTypeDefinitionNode $parentType) use ($originalDocument) {
                return collect($parentType->fields)->reduce(
                    function (DocumentAST $document, FieldDefinitionNode $parentField) use (
                        $parentType,
                        $originalDocument
                    ) {
                        return collect($parentField->arguments)->reduce(
                            function (DocumentAST $document, InputValueDefinitionNode $argDefinition) use (
                                $parentType,
                                $parentField,
                                $originalDocument
                            ) {
                                $argManipulators = graphql()->directives()->argManipulators($argDefinition);

                                return $argManipulators->reduce(
                                    function (DocumentAST $document, ArgManipulator $argManipulator) use (
                                        $argDefinition,
                                        $parentField,
                                        $parentType,
                                        $originalDocument
                                    ) {
                                        return $argManipulator->manipulateSchema($argDefinition, $parentField,
                                            $parentType, $document, $originalDocument);
                                    }, $document);
                            }, $document);
                    }, $document);
            }, $document);
    }

    /**
     * Inject the node type and a node field into Query.
     *
     * @param DocumentAST $document
     *
     * @throws \Nuwave\Lighthouse\Exceptions\ParseException
     * @throws \Nuwave\Lighthouse\Exceptions\DocumentASTException
     *
     * @return DocumentAST
     */
    protected static function addNodeSupport(DocumentAST $document): DocumentAST
    {
        $hasTypeImplementingNode = $document->objectTypeDefinitions()
            ->contains(function (ObjectTypeDefinitionNode $objectType) {
                return collect($objectType->interfaces)
                    ->contains(function (NamedTypeNode $interface) {
                        return 'Node' === $interface->name->value;
                    });
            });

        // Only add the node type and node field if a type actually implements them
        // Otherwise, a validation error is thrown
        if (! $hasTypeImplementingNode) {
            return $document;
        }

        $globalId = config('lighthouse.global_id_field', '_id');

        // Double slashes to escape the slashes in the namespace.
        $interface = PartialParser::interfaceTypeDefinition("
            \"Node global interface\"
            interface Node @interface(resolver: \"Nuwave\\\\Lighthouse\\\\Support\\\\Http\\\\GraphQL\\\\Interfaces\\\\NodeInterface@resolve\") {
              \"Global identifier that can be used to resolve any Node implementation.\"
              $globalId: ID!
            }
        ");
        $document->setDefinition($interface);

        $nodeQuery = PartialParser::fieldDefinition('node(id: ID!): Node @field(resolver: "Nuwave\\\Lighthouse\\\Support\\\Http\\\GraphQL\\\Queries\\\NodeQuery@resolve")');
        $document->addFieldToQueryType($nodeQuery);

        return $document;
    }

    /**
     * @param DocumentAST $document
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DocumentASTException
     * @throws \Nuwave\Lighthouse\Exceptions\ParseException
     *
     * @return DocumentAST
     */
    protected static function addPaginationInfoTypes(DocumentAST $document): DocumentAST
    {
        $paginatorInfo = PartialParser::objectTypeDefinition('
        type PaginatorInfo {
          "Total count of available items in the page."
          count: Int!
        
          "Current pagination page."
          currentPage: Int!
        
          "Index of first item in the current page."
          firstItem: Int!
        
          "If collection has more pages."
          hasMorePages: Boolean!
        
          "Index of last item in the current page."
          lastItem: Int!
        
          "Last page number of the collection."
          lastPage: Int!
        
          "Number of items per page in the collection."
          perPage: Int!
        
          "Total items available in the collection."
          total: Int!
        }
        ');
        $document->setDefinition($paginatorInfo);

        $pageInfo = PartialParser::objectTypeDefinition('
        type PageInfo {
          "When paginating forwards, are there more items?"
          hasNextPage: Boolean!
        
          "When paginating backwards, are there more items?"
          hasPreviousPage: Boolean!
        
          "When paginating backwards, the cursor to continue."
          startCursor: String
        
          "When paginating forwards, the cursor to continue."
          endCursor: String
        
          "Total number of node in connection."
          total: Int
        
          "Count of nodes in current request."
          count: Int
        
          "Current page of request."
          currentPage: Int
        
          "Last page in connection."
          lastPage: Int
        }
        ');
        $document->setDefinition($pageInfo);

        return $document;
    }
  
     /**
     * @param DocumentAST $document
     *
     * @return DocumentAST
     */
    protected static function applyExtensions(DocumentAST $document): DocumentAST
    {
        $originalDocument = $document;

        return graphql()
            ->extensions()
            ->active()
            ->reduce(function (
                DocumentAST $document,
                GraphQLExtension $extension
            ) use ($originalDocument) {
                return $extension->manipulateSchema($document, $originalDocument);
            }, $document);
    }
}
