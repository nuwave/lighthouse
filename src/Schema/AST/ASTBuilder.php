<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\AST;

use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\EnumTypeExtensionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeExtensionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeExtensionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeExtensionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeExtensionNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeExtensionNode;
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
use Nuwave\Lighthouse\Support\Contracts\InputFieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\TypeExtensionManipulator;
use Nuwave\Lighthouse\Support\Contracts\TypeManipulator;

class ASTBuilder
{
    public const EXTENSION_TO_DEFINITION_CLASS = [
        ObjectTypeExtensionNode::class => ObjectTypeDefinitionNode::class,
        InputObjectTypeExtensionNode::class => InputObjectTypeDefinitionNode::class,
        InterfaceTypeExtensionNode::class => InterfaceTypeDefinitionNode::class,
        ScalarTypeExtensionNode::class => ScalarTypeDefinitionNode::class,
        EnumTypeExtensionNode::class => EnumTypeDefinitionNode::class,
        UnionTypeExtensionNode::class => UnionTypeDefinitionNode::class,
    ];

    /** Initialized lazily in $this->documentAST(). */
    protected DocumentAST $documentAST;

    public function __construct(
        protected DirectiveLocator $directiveLocator,
        protected SchemaSourceProvider $schemaSourceProvider,
        protected EventsDispatcher $eventsDispatcher,
        protected ASTCache $astCache,
    ) {}

    public function documentAST(): DocumentAST
    {
        return $this->documentAST ??= $this->astCache->isEnabled()
            ? $this->astCache->fromCacheOrBuild(fn (): DocumentAST => $this->build())
            : $this->build();
    }

    public function build(): DocumentAST
    {
        $schemaString = $this->schemaSourceProvider->getSchemaString();

        // Allow registering listeners that inject additional schema definitions.
        // This can be used by plugins to hook into the schema building process
        // while still allowing the user to define their schema as usual.
        $additionalSchemas = (array) $this->eventsDispatcher->dispatch(
            new BuildSchemaString($schemaString),
        );

        $this->documentAST = DocumentAST::fromSource(
            implode(
                PHP_EOL,
                Arr::prepend($additionalSchemas, $schemaString),
            ),
        );

        // Apply transformations from directives
        $this->applyTypeDefinitionManipulators();
        $this->applyTypeExtensionManipulators();
        $this->applyFieldManipulators();
        $this->applyArgManipulators();
        $this->applyInputFieldManipulators();

        // Listeners may manipulate the DocumentAST that is passed by reference
        // into the ManipulateAST event. This can be useful for extensions
        // that want to programmatically change the schema.
        $this->eventsDispatcher->dispatch(
            new ManipulateAST($this->documentAST),
        );

        return $this->documentAST;
    }

    /** Apply directives on type definitions that can manipulate the AST. */
    protected function applyTypeDefinitionManipulators(): void
    {
        foreach ($this->documentAST->types as $typeDefinition) {
            foreach (
                $this->directiveLocator->associatedOfType($typeDefinition, TypeManipulator::class) as $typeManipulator
            ) {
                $typeManipulator->manipulateTypeDefinition($this->documentAST, $typeDefinition);
            }
        }
    }

    /** Apply directives on type extensions that can manipulate the AST. */
    protected function applyTypeExtensionManipulators(): void
    {
        foreach ($this->documentAST->typeExtensions as $typeName => $typeExtensionsList) {
            foreach ($typeExtensionsList as $typeExtension) {
                // Before we actually extend the types, we apply the manipulator directives
                // that are defined on type extensions themselves
                foreach (
                    $this->directiveLocator->associatedOfType($typeExtension, TypeExtensionManipulator::class) as $typeExtensionManipulator
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
                } elseif ($typeExtension instanceof ScalarTypeExtensionNode) {
                    $this->extendScalarType($typeName, $typeExtension);
                } elseif ($typeExtension instanceof EnumTypeExtensionNode) {
                    $this->extendEnumType($typeName, $typeExtension);
                } elseif ($typeExtension instanceof UnionTypeExtensionNode) {
                    $this->extendUnionType($typeName, $typeExtension);
                }
            }
        }
    }

    protected function extendObjectLikeType(string $typeName, ObjectTypeExtensionNode|InputObjectTypeExtensionNode|InterfaceTypeExtensionNode $typeExtension): void
    {
        $extendedObjectLikeType = $this->documentAST->types[$typeName] ?? null;
        if ($extendedObjectLikeType === null) {
            if (RootType::isRootType($typeName)) {
                $extendedObjectLikeType = Parser::objectTypeDefinition(/** @lang GraphQL */ "type {$typeName}");
                $this->documentAST->setTypeDefinition($extendedObjectLikeType);
            } else {
                throw new DefinitionException(
                    $this->missingBaseDefinition($typeName, $typeExtension),
                );
            }
        }

        assert($extendedObjectLikeType instanceof ObjectTypeDefinitionNode || $extendedObjectLikeType instanceof InputObjectTypeDefinitionNode || $extendedObjectLikeType instanceof InterfaceTypeDefinitionNode);

        $this->assertExtensionMatchesDefinition($typeExtension, $extendedObjectLikeType);

        // @phpstan-ignore-next-line we know the types of fields will match because we passed assertExtensionMatchesDefinition().
        $extendedObjectLikeType->fields = ASTHelper::mergeUniqueNodeList(
            // @phpstan-ignore-next-line
            $extendedObjectLikeType->fields,
            // @phpstan-ignore-next-line
            $typeExtension->fields,
        );
        $extendedObjectLikeType->directives = $extendedObjectLikeType->directives->merge($typeExtension->directives);

        if ($extendedObjectLikeType instanceof ObjectTypeDefinitionNode) {
            assert($typeExtension instanceof ObjectTypeExtensionNode, 'We know this because we passed assertExtensionMatchesDefinition().');
            $extendedObjectLikeType->interfaces = ASTHelper::mergeUniqueNodeList(
                $extendedObjectLikeType->interfaces,
                $typeExtension->interfaces,
            );
        }
    }

    protected function extendScalarType(string $typeName, ScalarTypeExtensionNode $typeExtension): void
    {
        $extendedScalar = $this->documentAST->types[$typeName]
            ?? throw new DefinitionException($this->missingBaseDefinition($typeName, $typeExtension));
        assert($extendedScalar instanceof ScalarTypeDefinitionNode);

        $this->assertExtensionMatchesDefinition($typeExtension, $extendedScalar);

        $extendedScalar->directives = $extendedScalar->directives->merge($typeExtension->directives);
    }

    protected function extendEnumType(string $typeName, EnumTypeExtensionNode $typeExtension): void
    {
        $extendedEnum = $this->documentAST->types[$typeName]
            ?? throw new DefinitionException($this->missingBaseDefinition($typeName, $typeExtension));
        assert($extendedEnum instanceof EnumTypeDefinitionNode);

        $this->assertExtensionMatchesDefinition($typeExtension, $extendedEnum);

        $extendedEnum->directives = $extendedEnum->directives->merge($typeExtension->directives);
        $extendedEnum->values = ASTHelper::mergeUniqueNodeList(
            $extendedEnum->values,
            $typeExtension->values,
        );
    }

    protected function extendUnionType(string $typeName, UnionTypeExtensionNode $typeExtension): void
    {
        $extendedUnion = $this->documentAST->types[$typeName]
            ?? throw new DefinitionException($this->missingBaseDefinition($typeName, $typeExtension));
        assert($extendedUnion instanceof UnionTypeDefinitionNode);

        $this->assertExtensionMatchesDefinition($typeExtension, $extendedUnion);

        $extendedUnion->types = ASTHelper::mergeUniqueNodeList(
            $extendedUnion->types,
            $typeExtension->types,
        );
    }

    protected function missingBaseDefinition(string $typeName, ObjectTypeExtensionNode|InputObjectTypeExtensionNode|InterfaceTypeExtensionNode|ScalarTypeExtensionNode|EnumTypeExtensionNode|UnionTypeExtensionNode $typeExtension): string
    {
        return "Could not find a base definition {$typeName} of kind {$typeExtension->kind} to extend.";
    }

    protected function assertExtensionMatchesDefinition(
        ObjectTypeExtensionNode|InputObjectTypeExtensionNode|InterfaceTypeExtensionNode|ScalarTypeExtensionNode|EnumTypeExtensionNode|UnionTypeExtensionNode $extension,
        ObjectTypeDefinitionNode|InputObjectTypeDefinitionNode|InterfaceTypeDefinitionNode|ScalarTypeDefinitionNode|EnumTypeDefinitionNode|UnionTypeDefinitionNode $definition,
    ): void {
        if (static::EXTENSION_TO_DEFINITION_CLASS[$extension::class] !== $definition::class) {
            throw new DefinitionException("The type extension {$extension->name->value} of kind {$extension->kind} can not extend a definition of kind {$definition->kind}.");
        }
    }

    /** Apply directives on fields that can manipulate the AST. */
    protected function applyFieldManipulators(): void
    {
        foreach ($this->documentAST->types as $typeDefinition) {
            if ($typeDefinition instanceof ObjectTypeDefinitionNode || $typeDefinition instanceof InterfaceTypeDefinitionNode) {
                foreach ($typeDefinition->fields as $fieldDefinition) {
                    foreach (
                        $this->directiveLocator->associatedOfType($fieldDefinition, FieldManipulator::class) as $fieldManipulator
                    ) {
                        $fieldManipulator->manipulateFieldDefinition($this->documentAST, $fieldDefinition, $typeDefinition);
                    }
                }
            }
        }
    }

    /** Apply directives on args that can manipulate the AST. */
    protected function applyArgManipulators(): void
    {
        foreach ($this->documentAST->types as $typeDefinition) {
            if ($typeDefinition instanceof ObjectTypeDefinitionNode || $typeDefinition instanceof InterfaceTypeDefinitionNode) {
                foreach ($typeDefinition->fields as $fieldDefinition) {
                    foreach ($fieldDefinition->arguments as $argumentDefinition) {
                        foreach (
                            $this->directiveLocator->associatedOfType($argumentDefinition, ArgManipulator::class) as $argManipulator
                        ) {
                            $argManipulator->manipulateArgDefinition(
                                $this->documentAST,
                                $argumentDefinition,
                                $fieldDefinition,
                                $typeDefinition,
                            );
                        }
                    }
                }
            }
        }
    }

    /** Apply directives on input fields that can manipulate the AST. */
    protected function applyInputFieldManipulators(): void
    {
        foreach ($this->documentAST->types as $typeDefinition) {
            if ($typeDefinition instanceof InputObjectTypeDefinitionNode) {
                foreach ($typeDefinition->fields as $fieldDefinition) {
                    foreach (
                        $this->directiveLocator->associatedOfType($fieldDefinition, InputFieldManipulator::class) as $inputFieldManipulator
                    ) {
                        $inputFieldManipulator->manipulateInputFieldDefinition(
                            $this->documentAST,
                            $fieldDefinition,
                            $typeDefinition,
                        );
                    }
                }
            }
        }
    }
}
