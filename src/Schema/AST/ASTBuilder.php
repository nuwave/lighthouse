<?php

namespace Nuwave\Lighthouse\Schema\AST;

use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\EnumTypeExtensionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeExtensionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeExtensionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeExtensionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\TypeExtensionNode;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Events\BuildSchemaString;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\Factories\DirectiveFactory;
use Nuwave\Lighthouse\Schema\RootType;
use Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\TypeExtensionManipulator;
use Nuwave\Lighthouse\Support\Contracts\TypeManipulator;

class ASTBuilder
{
    public const EXTENSION_TO_DEFINITION_CLASS = [
        ObjectTypeExtensionNode::class => ObjectTypeDefinitionNode::class,
        InputObjectTypeExtensionNode::class => InputObjectTypeDefinitionNode::class,
        InterfaceTypeExtensionNode::class => InterfaceTypeDefinitionNode::class,
        EnumTypeExtensionNode::class => EnumTypeDefinitionNode::class,
    ];

    /**
     * The directive factory.
     *
     * @var \Nuwave\Lighthouse\Schema\Factories\DirectiveFactory
     */
    protected $directiveFactory;

    /**
     * The schema source provider.
     *
     * @var \Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider
     */
    protected $schemaSourceProvider;

    /**
     * The event dispatcher.
     *
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    protected $eventDispatcher;

    /**
     * The config repository.
     *
     * @var ConfigRepository
     */
    protected $configRepository;

    /**
     * The document AST.
     *
     * Initialized lazily, is only set after documentAST() is called.
     *
     * @var \Nuwave\Lighthouse\Schema\AST\DocumentAST
     */
    protected $documentAST;

    public function __construct(
        DirectiveFactory $directiveFactory,
        SchemaSourceProvider $schemaSourceProvider,
        EventDispatcher $eventDispatcher,
        ConfigRepository $configRepository
    ) {
        $this->directiveFactory = $directiveFactory;
        $this->schemaSourceProvider = $schemaSourceProvider;
        $this->eventDispatcher = $eventDispatcher;
        $this->configRepository = $configRepository;
    }

    /**
     * Get the schema string and build an AST out of it.
     *
     * @return \Nuwave\Lighthouse\Schema\AST\DocumentAST
     */
    public function documentAST(): DocumentAST
    {
        if (isset($this->documentAST)) {
            return $this->documentAST;
        }

        $cacheConfig = $this->configRepository->get('lighthouse.cache');
        if ($cacheConfig['enable']) {
            /** @var \Illuminate\Contracts\Cache\Repository $cache */
            $cache = app('cache')->store($cacheConfig['store'] ?? null);
            $this->documentAST = $cache->remember(
                $cacheConfig['key'],
                // TODO remove this fallback in v5
                $cacheConfig['ttl'] ?? null,
                function (): DocumentAST {
                    return $this->build();
                }
            );
        } else {
            $this->documentAST = $this->build();
        }

        return $this->documentAST;
    }

    protected function build(): DocumentAST
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

        // TODO separate out into modules
        $this->addPaginationInfoTypes();
        $this->addNodeSupport();

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
     */
    protected function applyTypeExtensionManipulators(): void
    {
        foreach ($this->documentAST->typeExtensions as $typeName => $typeExtensionsList) {
            foreach ($typeExtensionsList as $typeExtension) {
                // Before we actually extend the types, we apply the manipulator directives
                // that are defined on type extensions themselves
                /** @var \Nuwave\Lighthouse\Support\Contracts\TypeExtensionManipulator $typeExtensionManipulator */
                foreach (
                    $this->directiveFactory->createAssociatedDirectivesOfType($typeExtension, TypeExtensionManipulator::class)
                    as $typeExtensionManipulator
                ) {
                    $typeExtensionManipulator->manipulateTypeExtension($this->documentAST, $typeExtension);
                }

                // After manipulation on the type extension has been done,
                // we can merge them with the original type
                if (
                    $typeExtension instanceof ObjectTypeExtensionNode
                    || $typeExtension instanceof InputObjectTypeExtensionNode
                    || $typeExtension instanceof InterfaceTypeExtensionNode
                ) {
                    $this->extendObjectLikeType($typeName, $typeExtension);
                } elseif ($typeExtension instanceof EnumTypeExtensionNode) {
                    $this->extendEnumType($typeName, $typeExtension);
                }
            }
        }
    }

    /**
     * @param  \GraphQL\Language\AST\ObjectTypeExtensionNode|\GraphQL\Language\AST\InputObjectTypeExtensionNode|\GraphQL\Language\AST\InterfaceTypeExtensionNode  $typeExtension
     */
    protected function extendObjectLikeType(string $typeName, TypeExtensionNode $typeExtension): void
    {
        /** @var \GraphQL\Language\AST\ObjectTypeDefinitionNode|\GraphQL\Language\AST\InputObjectTypeDefinitionNode|\GraphQL\Language\AST\InterfaceTypeDefinitionNode|null $extendedObjectLikeType */
        $extendedObjectLikeType = $this->documentAST->types[$typeName] ?? null;
        if ($extendedObjectLikeType === null) {
            if (RootType::isRootType($typeName)) {
                $extendedObjectLikeType = PartialParser::objectTypeDefinition(/** @lang GraphQL */ "type {$typeName}");
                $this->documentAST->setTypeDefinition($extendedObjectLikeType);
            } else {
                throw new DefinitionException(
                    $this->missingBaseDefinition($typeName, $typeExtension)
                );
            }
        }

        $this->assertExtensionMatchesDefinition($typeExtension, $extendedObjectLikeType);

        // @phpstan-ignore-next-line graphql-php types are unnecessarily nullable
        $extendedObjectLikeType->fields = ASTHelper::mergeUniqueNodeList(
            $extendedObjectLikeType->fields,
            $typeExtension->fields
        );
    }

    protected function extendEnumType(string $typeName, EnumTypeExtensionNode $typeExtension): void
    {
        /** @var \GraphQL\Language\AST\EnumTypeDefinitionNode|null $extendedEnum */
        $extendedEnum = $this->documentAST->types[$typeName] ?? null;
        if ($extendedEnum === null) {
            throw new DefinitionException(
                $this->missingBaseDefinition($typeName, $typeExtension)
            );
        }

        $this->assertExtensionMatchesDefinition($typeExtension, $extendedEnum);

        // @phpstan-ignore-next-line graphql-php types are unnecessarily nullable
        $extendedEnum->values = ASTHelper::mergeUniqueNodeList(
            $extendedEnum->values,
            $typeExtension->values
        );
    }

    /**
     * @param  \GraphQL\Language\AST\ObjectTypeExtensionNode|\GraphQL\Language\AST\InputObjectTypeExtensionNode|\GraphQL\Language\AST\InterfaceTypeExtensionNode|\GraphQL\Language\AST\EnumTypeExtensionNode  $typeExtension
     */
    protected function missingBaseDefinition(string $typeName, TypeExtensionNode $typeExtension): string
    {
        return "Could not find a base definition $typeName of kind {$typeExtension->kind} to extend.";
    }

    /**
     * @param  \GraphQL\Language\AST\ObjectTypeExtensionNode|\GraphQL\Language\AST\InputObjectTypeExtensionNode|\GraphQL\Language\AST\InterfaceTypeExtensionNode|\GraphQL\Language\AST\EnumTypeExtensionNode  $extension
     * @param  \GraphQL\Language\AST\ObjectTypeDefinitionNode|\GraphQL\Language\AST\InputObjectTypeDefinitionNode|\GraphQL\Language\AST\InterfaceTypeDefinitionNode|\GraphQL\Language\AST\EnumTypeDefinitionNode  $definition
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    protected function assertExtensionMatchesDefinition(TypeExtensionNode $extension, TypeDefinitionNode $definition): void
    {
        if (static::EXTENSION_TO_DEFINITION_CLASS[get_class($extension)] !== get_class($definition)) {
            throw new DefinitionException(
                "The type extension {$extension->name->value} of kind {$extension->kind} can not extend a definition of kind {$definition->kind}."
            );
        }
    }

    /**
     * Apply directives on fields that can manipulate the AST.
     */
    protected function applyFieldManipulators(): void
    {
        foreach ($this->documentAST->types as $typeDefinition) {
            if ($typeDefinition instanceof ObjectTypeDefinitionNode) {
                // @phpstan-ignore-next-line graphql-php types are unnecessarily nullable
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
     */
    protected function applyArgManipulators(): void
    {
        foreach ($this->documentAST->types as $typeDefinition) {
            if ($typeDefinition instanceof ObjectTypeDefinitionNode) {
                // @phpstan-ignore-next-line graphql-php types are unnecessarily nullable
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
     */
    protected function addPaginationInfoTypes(): void
    {
        $this->documentAST->setTypeDefinition(
            PartialParser::objectTypeDefinition(/** @lang GraphQL */ '
                "Pagination information about the corresponding list of items."
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
            PartialParser::objectTypeDefinition(/** @lang GraphQL */ '
                "Pagination information about the corresponding list of items."
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
     * Returns whether or not the given interface is used within the defined types.
     */
    protected function hasTypeImplementingInterface(string $interfaceName): bool
    {
        foreach ($this->documentAST->types as $typeDefinition) {
            if ($typeDefinition instanceof ObjectTypeDefinitionNode) {
                if (ASTHelper::typeImplementsInterface($typeDefinition, $interfaceName)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Inject the Node interface and a node field into the Query type.
     */
    protected function addNodeSupport(): void
    {
        // Only add the node type and node field if a type actually implements them
        // Otherwise, a validation error is thrown
        if (! $this->hasTypeImplementingInterface('Node')) {
            return;
        }

        $globalId = config('lighthouse.global_id_field');
        // Double slashes to escape the slashes in the namespace.
        $this->documentAST->setTypeDefinition(
            PartialParser::interfaceTypeDefinition(/** @lang GraphQL */ <<<GRAPHQL
"Node global interface"
interface Node @interface(resolveType: "Nuwave\\\Lighthouse\\\Schema\\\NodeRegistry@resolveType") {
"Global identifier that can be used to resolve any Node implementation."
$globalId: ID!
}
GRAPHQL
            )
        );

        /** @var \GraphQL\Language\AST\ObjectTypeDefinitionNode $queryType */
        $queryType = $this->documentAST->types[RootType::QUERY];
        // @phpstan-ignore-next-line graphql-php types are unnecessarily nullable
        $queryType->fields = ASTHelper::mergeNodeList(
            $queryType->fields,
            [
                PartialParser::fieldDefinition(/** @lang GraphQL */ '
                    node(id: ID! @globalId): Node @field(resolver: "Nuwave\\\Lighthouse\\\Schema\\\NodeRegistry@resolve")
                '),
            ]
        );
    }
}
