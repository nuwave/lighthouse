<?php

namespace Nuwave\Lighthouse\Schema\AST;

use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\DirectiveRegistry;
use Nuwave\Lighthouse\Exceptions\ParseException;
use GraphQL\Language\AST\ObjectTypeExtensionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Contracts\NodeManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Schema\Extensions\ExtensionRegistry;

class ASTBuilder
{
    /**
     * Convert the base schema string into an AST by applying different manipulations.
     *
     * @param string $schema
     *
     * @throws ParseException
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

        $document = self::addPaginationInfoTypes($document);
        $document = self::addNodeSupport($document);

        $document = resolve(ExtensionRegistry::class)->manipulate($document);

        return $document;
    }

    /**
     * @param DocumentAST $document
     *
     * @return DocumentAST
     */
    protected static function applyNodeManipulators(DocumentAST $document): DocumentAST
    {
        return $document
            ->typeDefinitions()
            // Iterate over both of those at once, as it does not matter at this point
            ->concat(
                $document->typeExtensions()
            )
            ->reduce(
                function (DocumentAST $document, Node $node) {
                    $nodeManipulators = resolve(DirectiveRegistry::class)->nodeManipulators($node);

                    return $nodeManipulators->reduce(
                        function (DocumentAST $document, NodeManipulator $nodeManipulator) use ($node) {
                            return $nodeManipulator->manipulateSchema($node, $document);
                        },
                        $document
                    );
                },
                $document
            );
    }

    /**
     * The final schema must not contain type extensions, so we merge them here.
     *
     * @param DocumentAST $document
     *
     * @return DocumentAST
     */
    protected static function mergeTypeExtensions(DocumentAST $document): DocumentAST
    {
        $document->objectTypeDefinitions()->each(
            function (ObjectTypeDefinitionNode $objectType) use ($document) {
                $name = $objectType->name->value;

                $objectType = $document
                    ->extensionsForType($name)
                    ->reduce(
                        function (ObjectTypeDefinitionNode $relatedObjectType, ObjectTypeExtensionNode $typeExtension) {
                            $relatedObjectType->fields = ASTHelper::mergeUniqueNodeList(
                                $relatedObjectType->fields,
                                $typeExtension->fields
                            );

                            return $relatedObjectType;
                        },
                        $objectType
                    );

                // Modify the original document by overwriting the definition with the merged one
                $document->setDefinition($objectType);
            }
        );

        return $document;
    }

    /**
     * @param DocumentAST $document
     *
     * @return DocumentAST
     */
    protected static function applyFieldManipulators(DocumentAST $document): DocumentAST
    {
        return $document->objectTypeDefinitions()->reduce(
            function (DocumentAST $document, ObjectTypeDefinitionNode $objectType) {
                return collect($objectType->fields)->reduce(
                    function (DocumentAST $document, FieldDefinitionNode $fieldDefinition) use ($objectType) {
                        $fieldManipulators = resolve(DirectiveRegistry::class)->fieldManipulators($fieldDefinition);

                        return $fieldManipulators->reduce(
                            function (DocumentAST $document, FieldManipulator $fieldManipulator) use ($fieldDefinition, $objectType) {
                                return $fieldManipulator->manipulateSchema($fieldDefinition, $objectType, $document);
                            },
                            $document
                        );
                    },
                    $document
                );
            },
            $document
        );
    }

    /**
     * @param DocumentAST $document
     *
     * @return DocumentAST
     */
    protected static function applyArgManipulators(DocumentAST $document): DocumentAST
    {
        return $document->objectTypeDefinitions()->reduce(
            function (DocumentAST $document, ObjectTypeDefinitionNode $parentType) {
                return collect($parentType->fields)->reduce(
                    function (DocumentAST $document, FieldDefinitionNode $parentField) use ($parentType) {
                        return collect($parentField->arguments)->reduce(
                            function (DocumentAST $document, InputValueDefinitionNode $argDefinition) use ($parentType, $parentField) {
                                $argManipulators = resolve(DirectiveRegistry::class)->argManipulators($argDefinition);

                                return $argManipulators->reduce(
                                    function (DocumentAST $document, ArgManipulator $argManipulator) use (
                                        $argDefinition,
                                        $parentField,
                                        $parentType
                                    ) {
                                        return $argManipulator->manipulateSchema(
                                            $argDefinition,
                                            $parentField,
                                            $parentType,
                                            $document
                                        );
                                    },
                                    $document
                                );
                            },
                            $document
                        );
                    },
                    $document
                );
            },
            $document
        );
    }

    /**
     * @param DocumentAST $document
     *
     * @throws ParseException
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
          firstItem: Int
        
          "If collection has more pages."
          hasMorePages: Boolean!
        
          "Index of last item in the current page."
          lastItem: Int
        
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
     * Inject the node type and a node field into Query.
     *
     * @param DocumentAST $document
     *
     * @throws ParseException
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

        $globalId = config('lighthouse.global_id_field');
        // Double slashes to escape the slashes in the namespace.
        $interface = PartialParser::interfaceTypeDefinition(<<<GRAPHQL
"Node global interface"	
interface Node @interface(resolveType: "Nuwave\\\Lighthouse\\\Schema\\\NodeRegistry@resolveType") {	
  "Global identifier that can be used to resolve any Node implementation."
  $globalId: ID!	
}	
GRAPHQL
);
        $document->setDefinition($interface);

        $nodeQuery = PartialParser::fieldDefinition(
            'node(id: ID! @globalId): Node @field(resolver: "Nuwave\\\Lighthouse\\\Schema\\\NodeRegistry@resolve")'
        );
        $document->addFieldToQueryType($nodeQuery);

        return $document;
    }
}
