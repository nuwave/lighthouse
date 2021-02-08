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
use GraphQL\Language\Parser;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Events\BuildSchemaString;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\DirectiveLocator;
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
     * @var \Nuwave\Lighthouse\Schema\DirectiveLocator
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
        DirectiveLocator $directiveFactory,
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
        if ($this->documentAST !== null) {
            return $this->documentAST;
        }

        $cacheConfig = $this->configRepository->get('lighthouse.cache');
        if ($cacheConfig['enable']) {
            /** @var \Illuminate\Contracts\Cache\Repository $cache */
            $cache = app('cache')->store($cacheConfig['store'] ?? null);
            $this->documentAST = $cache->remember(
                $cacheConfig['key'],
                $cacheConfig['ttl'],
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
                $this->directiveFactory->associatedOfType($typeDefinition, TypeManipulator::class)
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
                    $this->directiveFactory->associatedOfType($typeExtension, TypeExtensionManipulator::class)
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
                $extendedObjectLikeType = Parser::objectTypeDefinition(/** @lang GraphQL */ "type {$typeName}");
                $this->documentAST->setTypeDefinition($extendedObjectLikeType);
            } else {
                throw new DefinitionException(
                    $this->missingBaseDefinition($typeName, $typeExtension)
                );
            }
        }

        $this->assertExtensionMatchesDefinition($typeExtension, $extendedObjectLikeType);

        // @phpstan-ignore-next-line we know the types of fields will match because we passed assertExtensionMatchesDefinition().
        $extendedObjectLikeType->fields = ASTHelper::mergeUniqueNodeList(
            // @phpstan-ignore-next-line
            $extendedObjectLikeType->fields,
            // @phpstan-ignore-next-line
            $typeExtension->fields
        );

        if ($extendedObjectLikeType instanceof ObjectTypeDefinitionNode) {
            /**
             * We know this because we passed assertExtensionMatchesDefinition().
             *
             * @var \GraphQL\Language\AST\ObjectTypeExtensionNode $typeExtension
             */
            $extendedObjectLikeType->interfaces = ASTHelper::mergeUniqueNodeList(
                $extendedObjectLikeType->interfaces,
                $typeExtension->interfaces
            );
        }
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
                foreach ($typeDefinition->fields as $fieldDefinition) {
                    /** @var \Nuwave\Lighthouse\Support\Contracts\FieldManipulator $fieldManipulator */
                    foreach (
                        $this->directiveFactory->associatedOfType($fieldDefinition, FieldManipulator::class)
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
                foreach ($typeDefinition->fields as $fieldDefinition) {
                    foreach ($fieldDefinition->arguments as $argumentDefinition) {
                        /** @var \Nuwave\Lighthouse\Support\Contracts\ArgManipulator $argManipulator */
                        foreach (
                            $this->directiveFactory->associatedOfType($argumentDefinition, ArgManipulator::class)
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
}
