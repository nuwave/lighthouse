<?php

namespace Nuwave\Lighthouse\Schema\AST;

use GraphQL\Language\AST\Node;
use Illuminate\Support\Collection;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeExtensionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Schema\Factories\DirectiveFactory;
use Nuwave\Lighthouse\Support\Contracts\NodeManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;

class ASTBuilder
{
    /**
     * @var \Nuwave\Lighthouse\Schema\Factories\DirectiveFactory
     */
    protected $directiveFactory;

    /**
     * ASTBuilder constructor.
     *
     * @param  \Nuwave\Lighthouse\Schema\Factories\DirectiveFactory  $directiveFactory
     * @return void
     */
    public function __construct(DirectiveFactory $directiveFactory)
    {
        $this->directiveFactory = $directiveFactory;
    }

    /**
     * Convert the base schema string into an AST by applying different manipulations.
     *
     * @param  string  $schema
     * @return \Nuwave\Lighthouse\Schema\AST\DocumentAST
     */
    public function build(string $schema): DocumentAST
    {
        $document = DocumentAST::fromSource($schema);

        // Node manipulators may be defined on type extensions
        $document = $this->applyNodeManipulators($document);
        // After they have been applied, we can safely merge them
        $document = $this->mergeTypeExtensions($document);

        $document = $this->applyFieldManipulators($document);
        $document = $this->applyArgManipulators($document);

        $document = $this->addPaginationInfoTypes($document);

        $document = $this->addOrderByTypes($document);

        return $this->addNodeSupport($document);
    }

    /**
     * @param  \Nuwave\Lighthouse\Schema\AST\DocumentAST  $document
     * @return \Nuwave\Lighthouse\Schema\AST\DocumentAST
     */
    protected function applyNodeManipulators(DocumentAST $document): DocumentAST
    {
        return $document
            ->typeDefinitions()
            // Iterate over both of those at once, as it does not matter at this point
            ->concat(
                $document->typeExtensions()
            )
            ->reduce(
                function (DocumentAST $document, Node $node): DocumentAST {
                    return $this->directiveFactory
                        ->createNodeManipulators($node)
                        ->reduce(
                            function (DocumentAST $document, NodeManipulator $nodeManipulator) use ($node): DocumentAST {
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
     * @param  \Nuwave\Lighthouse\Schema\AST\DocumentAST  $document
     * @return \Nuwave\Lighthouse\Schema\AST\DocumentAST
     */
    protected function mergeTypeExtensions(DocumentAST $document): DocumentAST
    {
        $document->objectTypeDefinitions()->each(
            function (ObjectTypeDefinitionNode $objectType) use ($document) {
                $name = $objectType->name->value;

                $objectType = $document
                    ->extensionsForType($name)
                    ->reduce(
                        function (ObjectTypeDefinitionNode $relatedObjectType, ObjectTypeExtensionNode $typeExtension): ObjectTypeDefinitionNode {
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
     * @param  \Nuwave\Lighthouse\Schema\AST\DocumentAST  $document
     * @return \Nuwave\Lighthouse\Schema\AST\DocumentAST
     */
    protected function applyFieldManipulators(DocumentAST $document): DocumentAST
    {
        return $document->objectTypeDefinitions()->reduce(
            function (DocumentAST $document, ObjectTypeDefinitionNode $objectType): DocumentAST {
                return (new Collection($objectType->fields))->reduce(
                    function (DocumentAST $document, FieldDefinitionNode $fieldDefinition) use ($objectType): DocumentAST {
                        return $this->directiveFactory
                            ->createFieldManipulators($fieldDefinition)
                            ->reduce(
                                function (DocumentAST $document, FieldManipulator $fieldManipulator) use ($fieldDefinition, $objectType): DocumentAST {
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
     * @param  \Nuwave\Lighthouse\Schema\AST\DocumentAST  $document
     * @return \Nuwave\Lighthouse\Schema\AST\DocumentAST
     */
    protected function applyArgManipulators(DocumentAST $document): DocumentAST
    {
        return $document->objectTypeDefinitions()->reduce(
            function (DocumentAST $document, ObjectTypeDefinitionNode $parentType): DocumentAST {
                return (new Collection($parentType->fields))->reduce(
                    function (DocumentAST $document, FieldDefinitionNode $parentField) use ($parentType): DocumentAST {
                        return (new Collection($parentField->arguments))->reduce(
                            function (DocumentAST $document, InputValueDefinitionNode $argDefinition) use ($parentType, $parentField): DocumentAST {
                                return $this->directiveFactory
                                    ->createArgManipulators($argDefinition)
                                    ->reduce(
                                        function (DocumentAST $document, ArgManipulator $argManipulator) use (
                                            $argDefinition,
                                            $parentField,
                                            $parentType
                                        ): DocumentAST {
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
     * @param  \Nuwave\Lighthouse\Schema\AST\DocumentAST  $document
     * @return \Nuwave\Lighthouse\Schema\AST\DocumentAST
     */
    protected function addPaginationInfoTypes(DocumentAST $document): DocumentAST
    {
        return $document
            ->setDefinition(
                PartialParser::objectTypeDefinition('
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
                ')
            )
            ->setDefinition(
                PartialParser::objectTypeDefinition('
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
                ')
            );
    }

    /**
     * Inject the node type and a node field into Query.
     *
     * @param  \Nuwave\Lighthouse\Schema\AST\DocumentAST  $document
     * @return \Nuwave\Lighthouse\Schema\AST\DocumentAST
     */
    protected function addNodeSupport(DocumentAST $document): DocumentAST
    {
        $hasTypeImplementingNode = $document
            ->objectTypeDefinitions()
            ->contains(function (ObjectTypeDefinitionNode $objectType): bool {
                return (new Collection($objectType->interfaces))
                    ->contains(function (NamedTypeNode $interface): bool {
                        return $interface->name->value === 'Node';
                    });
            });

        // Only add the node type and node field if a type actually implements them
        // Otherwise, a validation error is thrown
        if (! $hasTypeImplementingNode) {
            return $document;
        }

        $globalId = config('lighthouse.global_id_field');
        // Double slashes to escape the slashes in the namespace.
        return $document
            ->setDefinition(
                PartialParser::interfaceTypeDefinition(<<<GRAPHQL
"Node global interface"	
interface Node @interface(resolveType: "Nuwave\\\Lighthouse\\\Schema\\\NodeRegistry@resolveType") {	
  "Global identifier that can be used to resolve any Node implementation."
  $globalId: ID!	
}	
GRAPHQL
                )
            )
            ->addFieldToQueryType(
                PartialParser::fieldDefinition('
                    node(id: ID! @globalId): Node @field(resolver: "Nuwave\\\Lighthouse\\\Schema\\\NodeRegistry@resolve")
                ')
            );
    }

    /**
     * Add types that are used for the @orderBy directive.
     *
     * @see \Nuwave\Lighthouse\Schema\Directives\OrderByDirective
     *
     * @param  \Nuwave\Lighthouse\Schema\AST\DocumentAST  $document
     * @return \Nuwave\Lighthouse\Schema\AST\DocumentAST
     */
    protected function addOrderByTypes(DocumentAST $document): DocumentAST
    {
        return $document
            ->setDefinition(
                PartialParser::enumTypeDefinition('
                    enum SortOrder {
                        ASC
                        DESC
                    }
                '
                )
            )
            ->setDefinition(
                PartialParser::inputObjectTypeDefinition('
                    input OrderByClause {
                       field: String!
                       order: SortOrder!
                    }
                ')
            );
    }
}
