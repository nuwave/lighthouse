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
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
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
     * @var \Nuwave\Lighthouse\Schema\DirectiveLocator
     */
    protected $directiveLocator;

    /**
     * @var \Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider
     */
    protected $schemaSourceProvider;

    /**
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    protected $eventsDispatcher;

    /**
     * @var \Nuwave\Lighthouse\Schema\AST\ASTCache
     */
    protected $astCache;

    /**
     * Initialized lazily in $this->documentAST().
     *
     * @var \Nuwave\Lighthouse\Schema\AST\DocumentAST
     */
    protected $documentAST;

    public function __construct(
        DirectiveLocator $directiveLocator,
        SchemaSourceProvider $schemaSourceProvider,
        EventsDispatcher $eventsDispatcher,
        ASTCache $astCache
    ) {
        $this->directiveLocator = $directiveLocator;
        $this->schemaSourceProvider = $schemaSourceProvider;
        $this->eventsDispatcher = $eventsDispatcher;
        $this->astCache = $astCache;
    }

    public function documentAST(): DocumentAST
    {
        if (! isset($this->documentAST)) {
            return $this->documentAST = $this->astCache->isEnabled()
                ? $this->astCache->fromCacheOrBuild(function (): DocumentAST {
                    return $this->build();
                })
                : $this->build();
        }

        return $this->documentAST;
    }

    public function build(): DocumentAST
    {
        $schemaString = $this->schemaSourceProvider->getSchemaString();

        // Allow registering listeners that inject additional schema definitions.
        // This can be used by plugins to hook into the schema building process
        // while still allowing the user to define their schema as usual.
        $additionalSchemas = (array) $this->eventsDispatcher->dispatch(
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
        $this->eventsDispatcher->dispatch(
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
                $this->directiveLocator->associatedOfType($typeDefinition, TypeManipulator::class)
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
                    $this->directiveLocator->associatedOfType($typeExtension, TypeExtensionManipulator::class)
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
        if (null === $extendedObjectLikeType) {
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
        if (null === $extendedEnum) {
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
        return "Could not find a base definition {$typeName} of kind {$typeExtension->kind} to extend.";
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
                        $this->directiveLocator->associatedOfType($fieldDefinition, FieldManipulator::class)
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
                            $this->directiveLocator->associatedOfType($argumentDefinition, ArgManipulator::class)
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
