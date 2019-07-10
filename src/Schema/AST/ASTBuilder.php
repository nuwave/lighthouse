<?php

namespace Nuwave\Lighthouse\Schema\AST;

use Illuminate\Support\Arr;
use GraphQL\Language\AST\NamedTypeNode;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Events\BuildSchemaString;
use GraphQL\Language\AST\ObjectTypeExtensionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Schema\Factories\DirectiveFactory;
use Nuwave\Lighthouse\Support\Contracts\TypeManipulator;
use Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Nuwave\Lighthouse\Support\Contracts\TypeExtensionManipulator;

class ASTBuilder
{
    /**
     * The directive factory.
     *
     * @var \Nuwave\Lighthouse\Schema\Factories\DirectiveFactory
     */
    protected $directiveFactory;

    /**
     * The event dispatcher.
     *
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    protected $eventDispatcher;

    /**
     * The schema source provider.
     *
     * @var \Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider
     */
    protected $schemaSourceProvider;

    /**
     * The document AST.
     *
     * @var \Nuwave\Lighthouse\Schema\AST\DocumentAST
     */
    protected $documentAST;

    /**
     * ASTBuilder constructor.
     *
     * @param  \Nuwave\Lighthouse\Schema\Factories\DirectiveFactory  $directiveFactory
     * @param  \Illuminate\Contracts\Events\Dispatcher  $eventDispatcher
     * @param  \Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider  $schemaSourceProvider
     * @return void
     */
    public function __construct(
        DirectiveFactory $directiveFactory,
        EventDispatcher $eventDispatcher,
        SchemaSourceProvider $schemaSourceProvider
    ) {
        $this->directiveFactory = $directiveFactory;
        $this->eventDispatcher = $eventDispatcher;
        $this->schemaSourceProvider = $schemaSourceProvider;
    }

    /**
     * Get the schema string and build an AST out of it.
     *
     * @return \Nuwave\Lighthouse\Schema\AST\DocumentAST
     */
    public function build(): DocumentAST
    {
        $schemaString = $this->schemaSourceProvider->getSchemaString();

        // Allow to register listeners that add in additional schema definitions.
        // This can be used by plugins to hook into the schema building process
        // while still allowing the user to add in their schema as usual.
        $additionalSchemas = (array) $this->eventDispatcher->dispatch(
            new BuildSchemaString($schemaString)
        );

        $this->documentAST = DocumentAST::fromSource(
            implode(
                PHP_EOL,
                Arr::prepend($additionalSchemas, $schemaString)
            )
        );

        // Apply transformations from directives
        $this->applyTypeDefinitionManipulators();
        $this->applyTypeExtensionManipulators();
        $this->applyFieldManipulators();
        $this->applyArgManipulators();

        $this->addPaginationInfoTypes();
        $this->addNodeSupport();
        $this->addOrderByTypes();

        // Listeners may manipulate the DocumentAST that is passed by reference
        // into the ManipulateAST event. This can be useful for extensions
        // that want to programmatically change the schema.
        $this->eventDispatcher->dispatch(
            new ManipulateAST($this->documentAST)
        );

        return $this->documentAST;
    }

    /**
     * Apply directives on type definitions that can manipulate the AST.
     *
     * @return void
     */
    protected function applyTypeDefinitionManipulators(): void
    {
        foreach ($this->documentAST->types as $typeDefinition) {
            /** @var \Nuwave\Lighthouse\Support\Contracts\TypeManipulator $typeDefinitionManipulator */
            foreach (
                $this->directiveFactory->createAssociatedDirectivesOfType($typeDefinition, TypeManipulator::class)
                as $typeDefinitionManipulator
            ) {
                $typeDefinitionManipulator->manipulateTypeDefinition($this->documentAST, $typeDefinition);
            }
        }
    }

    /**
     * Apply directives on type extensions that can manipulate the AST.
     *
     * @return void
     */
    protected function applyTypeExtensionManipulators(): void
    {
        foreach ($this->documentAST->typeExtensions as $typeName => $typeExtensionsList) {
            /** @var \GraphQL\Language\AST\TypeExtensionNode $typeExtension */
            foreach ($typeExtensionsList as $typeExtension) {
                /** @var \Nuwave\Lighthouse\Support\Contracts\TypeExtensionManipulator $typeExtensionManipulator */
                foreach (
                    $this->directiveFactory->createAssociatedDirectivesOfType($typeExtension, TypeExtensionManipulator::class)
                    as $typeExtensionManipulator
                ) {
                    $typeExtensionManipulator->manipulatetypeExtension($this->documentAST, $typeExtension);
                }

                // After manipulation on the type extension has been done,
                // we can merge its fields with the original type
                if ($typeExtension instanceof ObjectTypeExtensionNode) {
                    $relatedObjectType = $this->documentAST->types[$typeName];

                    $relatedObjectType->fields = ASTHelper::mergeUniqueNodeList(
                        $relatedObjectType->fields,
                        $typeExtension->fields
                    );
                }
            }
        }
    }

    /**
     * Apply directives on fields that can manipulate the AST.
     *
     * @return void
     */
    protected function applyFieldManipulators(): void
    {
        foreach ($this->documentAST->types as $typeDefinition) {
            if ($typeDefinition instanceof ObjectTypeDefinitionNode) {
                foreach ($typeDefinition->fields as $fieldDefinition) {
                    /** @var \Nuwave\Lighthouse\Support\Contracts\FieldManipulator $fieldManipulator */
                    foreach (
                        $this->directiveFactory->createAssociatedDirectivesOfType($fieldDefinition, FieldManipulator::class)
                        as $fieldManipulator
                    ) {
                        $fieldManipulator->manipulateFieldDefinition($this->documentAST, $fieldDefinition, $typeDefinition);
                    }
                }
            }
        }
    }

    /**
     * Apply directives on args that can manipulate the AST.
     *
     * @return void
     */
    protected function applyArgManipulators(): void
    {
        foreach ($this->documentAST->types as $typeDefinition) {
            if ($typeDefinition instanceof ObjectTypeDefinitionNode) {
                foreach ($typeDefinition->fields as $fieldDefinition) {
                    foreach ($fieldDefinition->arguments as $argumentDefinition) {
                        /** @var \Nuwave\Lighthouse\Support\Contracts\ArgManipulator $argManipulator */
                        foreach (
                            $this->directiveFactory->createAssociatedDirectivesOfType($argumentDefinition, ArgManipulator::class)
                            as $argManipulator
                        ) {
                            $argManipulator->manipulateArgDefinition(
                                $this->documentAST,
                                $argumentDefinition,
                                $fieldDefinition,
                                $typeDefinition
                            );
                        }
                    }
                }
            }
        }
    }

    /**
     * Add the types required for pagination.
     *
     * @return void
     */
    protected function addPaginationInfoTypes(): void
    {
        $this->documentAST->setTypeDefinition(
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
        );

        $this->documentAST->setTypeDefinition(
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
     * Inject the Node interface and a node field into the Query type.
     *
     * @return void
     */
    protected function addNodeSupport(): void
    {
        $hasTypeImplementingNode = false;

        foreach ($this->documentAST->types as $typeDefinition) {
            if ($typeDefinition instanceof ObjectTypeDefinitionNode) {
                /** @var NamedTypeNode $interface */
                foreach ($typeDefinition->interfaces as $interface) {
                    if ($interface->name->value === 'Node') {
                        $hasTypeImplementingNode = true;
                        break 2;
                    }
                }
            }
        }

        // Only add the node type and node field if a type actually implements them
        // Otherwise, a validation error is thrown
        if (! $hasTypeImplementingNode) {
            return;
        }

        $globalId = config('lighthouse.global_id_field');
        // Double slashes to escape the slashes in the namespace.
        $this->documentAST->setTypeDefinition(
            PartialParser::interfaceTypeDefinition(<<<GRAPHQL
"Node global interface"	
interface Node @interface(resolveType: "Nuwave\\\Lighthouse\\\Schema\\\NodeRegistry@resolveType") {	
"Global identifier that can be used to resolve any Node implementation."
$globalId: ID!	
}	
GRAPHQL
            )
        );

        /** @var ObjectTypeDefinitionNode $queryType */
        $queryType = $this->documentAST->types['Query'];
        $queryType->fields = ASTHelper::mergeNodeList(
            $queryType->fields,
            [
                PartialParser::fieldDefinition('
                    node(id: ID! @globalId): Node @field(resolver: "Nuwave\\\Lighthouse\\\Schema\\\NodeRegistry@resolve")
                '),
            ]
        );
    }

    /**
     * Add types that are used for the @orderBy directive.
     *
     * @see \Nuwave\Lighthouse\Schema\Directives\OrderByDirective
     *
     * @return void
     */
    protected function addOrderByTypes(): void
    {
        $this->documentAST->setTypeDefinition(
            PartialParser::enumTypeDefinition('
                enum SortOrder {
                    ASC
                    DESC
                }
            '
            )
        );

        $this->documentAST->setTypeDefinition(
            PartialParser::inputObjectTypeDefinition('
                input OrderByClause {
                   field: String!
                   order: SortOrder!
                }
            ')
        );
    }
}
