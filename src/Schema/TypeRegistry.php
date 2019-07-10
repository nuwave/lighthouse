<?php

namespace Nuwave\Lighthouse\Schema;

use Closure;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Support\Utils;
use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use Nuwave\Lighthouse\Support\Pipeline;
use GraphQL\Type\Definition\InterfaceType;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\TypeValue;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\EnumValueDefinitionNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Schema\Factories\FieldFactory;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Support\Contracts\TypeResolver;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Schema\Directives\UnionDirective;
use Nuwave\Lighthouse\Schema\Factories\ArgumentFactory;
use Nuwave\Lighthouse\Support\Contracts\TypeMiddleware;
use Nuwave\Lighthouse\Schema\Factories\DirectiveFactory;
use Nuwave\Lighthouse\Schema\Directives\InterfaceDirective;

class TypeRegistry
{
    /**
     * @var \Nuwave\Lighthouse\Support\Pipeline
     */
    protected $pipeline;

    /**
     * @var \Nuwave\Lighthouse\Schema\Factories\DirectiveFactory
     */
    protected $directiveFactory;

    /**
     * @var \Nuwave\Lighthouse\Schema\Factories\ArgumentFactory
     */
    protected $argumentFactory;

    /**
     * @var \GraphQL\Type\Definition\Type[]
     */
    protected $types;

    /**
     * @var \Nuwave\Lighthouse\Schema\AST\DocumentAST
     */
    protected $documentAST;

    /**
     * @param  \Nuwave\Lighthouse\Support\Pipeline  $pipeline
     * @param  \Nuwave\Lighthouse\Schema\Factories\DirectiveFactory  $directiveFactory
     * @param  \Nuwave\Lighthouse\Schema\Factories\ArgumentFactory  $argumentFactory
     * @return void
     */
    public function __construct(
        Pipeline $pipeline,
        DirectiveFactory $directiveFactory,
        ArgumentFactory $argumentFactory
    ) {
        $this->pipeline = $pipeline;
        $this->directiveFactory = $directiveFactory;
        $this->argumentFactory = $argumentFactory;
    }

    /**
     * @param  \Nuwave\Lighthouse\Schema\AST\DocumentAST  $documentAST
     * @return $this
     */
    public function setDocumentAST(DocumentAST $documentAST): self
    {
        $this->documentAST = $documentAST;

        return $this;
    }

    /**
     * Get the given GraphQL type by name.
     *
     * @param  string  $name
     * @return \GraphQL\Type\Definition\Type
     */
    public function get(string $name): Type
    {
        if (! isset($this->types[$name])) {
            $this->types[$name] = $this->handle(
                $this->documentAST->types[$name]
            );
        }

        return $this->types[$name];
    }

    /**
     * Register an executable GraphQL type.
     *
     * @param  \GraphQL\Type\Definition\Type  $type
     * @return $this
     */
    public function register(Type $type): self
    {
        $this->types[$type->name] = $type;

        return $this;
    }

    /**
     * Return all possible types that are registered.
     *
     * @return \GraphQL\Type\Definition\Type[]
     */
    public function possibleTypes(): array
    {
        // Make sure all the types from the AST are eagerly converted
        /** @var TypeDefinitionNode $typeDefinition */
        foreach ($this->documentAST->types as $typeDefinition) {
            $name = $typeDefinition->name->value;

            if (! isset($this->types[$name])) {
                $this->types[$name] = $this->handle($typeDefinition);
            }
        }

        return $this->types;
    }

    /**
     * Transform a definition node to an executable type.
     *
     * @param  \GraphQL\Language\AST\TypeDefinitionNode  $definition
     * @return \GraphQL\Type\Definition\Type
     */
    public function handle(TypeDefinitionNode $definition): Type
    {
        $typeValue = new TypeValue($definition);

        return $this->pipeline
            ->send($typeValue)
            ->through(
                $this->directiveFactory->createAssociatedDirectivesOfType($definition, TypeMiddleware::class)
            )
            ->via('handleNode')
            ->then(function (TypeValue $value) use ($definition): Type {
                /** @var \Nuwave\Lighthouse\Support\Contracts\TypeResolver $typeResolver */
                $typeResolver = $this->directiveFactory->createSingleDirectiveOfType($definition, TypeResolver::class);

                if ($typeResolver) {
                    return $typeResolver->resolveNode($value);
                }

                return $this->resolveType($definition);
            });
    }

    /**
     * The default type transformations.
     *
     * @param  \GraphQL\Language\AST\TypeDefinitionNode  $typeDefinition
     * @return \GraphQL\Type\Definition\Type
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    protected function resolveType(TypeDefinitionNode $typeDefinition): Type
    {
        // Ignore TypeExtensionNode since they are merged before we get here
        switch (get_class($typeDefinition)) {
            case EnumTypeDefinitionNode::class:
                return $this->resolveEnumType($typeDefinition);
            case ScalarTypeDefinitionNode::class:
                return $this->resolveScalarType($typeDefinition);
            case ObjectTypeDefinitionNode::class:
                return $this->resolveObjectType($typeDefinition);
            case InputObjectTypeDefinitionNode::class:
                return $this->resolveInputObjectType($typeDefinition);
            case InterfaceTypeDefinitionNode::class:
                return $this->resolveInterfaceType($typeDefinition);
            case UnionTypeDefinitionNode::class:
                return $this->resolveUnionType($typeDefinition);
            default:
                throw new InvariantViolation(
                    "Unknown type for definition [{$typeDefinition->name->value}]"
                );
        }
    }

    /**
     * @param  \GraphQL\Language\AST\EnumTypeDefinitionNode  $enumDefinition
     * @return \GraphQL\Type\Definition\EnumType
     */
    protected function resolveEnumType(EnumTypeDefinitionNode $enumDefinition): EnumType
    {
        return new EnumType([
            'name' => $enumDefinition->name->value,
            'description' => data_get($enumDefinition->description, 'value'),
            'values' => (new Collection($enumDefinition->values))
                ->mapWithKeys(function (EnumValueDefinitionNode $field): array {
                    // Get the directive that is defined on the field itself
                    $directive = ASTHelper::directiveDefinition($field, 'enum');

                    return [
                        $field->name->value => [
                            // If no explicit value is given, we default to the field name
                            'value' => $directive
                                ? ASTHelper::directiveArgValue($directive, 'value')
                                : $field->name->value,
                            'description' => data_get($field->description, 'value'),
                        ],
                    ];
                })
                ->toArray(),
        ]);
    }

    /**
     * @param  \GraphQL\Language\AST\ScalarTypeDefinitionNode  $scalarDefinition
     * @return \GraphQL\Type\Definition\ScalarType
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    protected function resolveScalarType(ScalarTypeDefinitionNode $scalarDefinition): ScalarType
    {
        $scalarName = $scalarDefinition->name->value;

        if ($directive = ASTHelper::directiveDefinition($scalarDefinition, 'scalar')) {
            $className = ASTHelper::directiveArgValue($directive, 'class');
        } else {
            $className = $scalarName;
        }

        $className = Utils::namespaceClassname(
            $className,
            (array) config('lighthouse.namespaces.scalars'),
            function (string $className): bool {
                return is_subclass_of($className, ScalarType::class);
            }
        );

        if (! $className) {
            throw new DefinitionException(
                "No matching subclass of GraphQL\Type\Definition\ScalarType of found for the scalar {$scalarName}"
            );
        }

        return new $className([
            'name' => $scalarName,
            'description' => data_get($scalarDefinition->description, 'value'),
        ]);
    }

    /**
     * @param  \GraphQL\Language\AST\ObjectTypeDefinitionNode  $objectDefinition
     * @return \GraphQL\Type\Definition\ObjectType
     */
    protected function resolveObjectType(ObjectTypeDefinitionNode $objectDefinition): ObjectType
    {
        return new ObjectType([
            'name' => $objectDefinition->name->value,
            'description' => data_get($objectDefinition->description, 'value'),
            'fields' => $this->resolveFieldsFunction($objectDefinition),
            'interfaces' => function () use ($objectDefinition): array {
                return (new Collection($objectDefinition->interfaces))
                    ->map(function (NamedTypeNode $interface): Type {
                        return $this->get($interface->name->value);
                    })
                    ->toArray();
            },
        ]);
    }

    /**
     * Returns a closure that lazy loads the fields for a constructed type.
     *
     * @param  \GraphQL\Language\AST\ObjectTypeDefinitionNode|\GraphQL\Language\AST\InterfaceTypeDefinitionNode  $definition
     * @return \Closure
     */
    protected function resolveFieldsFunction($definition): Closure
    {
        return function () use ($definition): array {
            return (new Collection($definition->fields))
                ->mapWithKeys(function (FieldDefinitionNode $fieldDefinition) use ($definition): array {
                    $fieldValue = new FieldValue(
                        new TypeValue($definition),
                        $fieldDefinition
                    );

                    return [
                        $fieldDefinition->name->value => app(FieldFactory::class)->handle($fieldValue),
                    ];
                })
                ->toArray();
        };
    }

    /**
     * @param  \GraphQL\Language\AST\InputObjectTypeDefinitionNode  $inputDefinition
     * @return \GraphQL\Type\Definition\InputObjectType
     */
    protected function resolveInputObjectType(InputObjectTypeDefinitionNode $inputDefinition): InputObjectType
    {
        return new InputObjectType([
            'name' => $inputDefinition->name->value,
            'description' => data_get($inputDefinition->description, 'value'),
            'fields' => function () use ($inputDefinition): array {
                return (new Collection($inputDefinition->fields))
                    ->mapWithKeys(function (InputValueDefinitionNode $inputValueDefinition) {
                        $argumentValue = new ArgumentValue($inputValueDefinition);

                        return [
                            $inputValueDefinition->name->value => $this->argumentFactory->handle($argumentValue),
                        ];
                    })
                    ->toArray();
            },
        ]);
    }

    /**
     * @param  \GraphQL\Language\AST\InterfaceTypeDefinitionNode  $interfaceDefinition
     * @return \GraphQL\Type\Definition\InterfaceType
     */
    protected function resolveInterfaceType(InterfaceTypeDefinitionNode $interfaceDefinition): InterfaceType
    {
        $nodeName = $interfaceDefinition->name->value;

        if ($directive = ASTHelper::directiveDefinition($interfaceDefinition, 'interface')) {
            $interfaceDirective = (new InterfaceDirective)->hydrate($interfaceDefinition);

            $typeResolver = $interfaceDirective->getResolverFromArgument('resolveType');
        } else {
            $interfaceClass = Utils::namespaceClassname(
                $nodeName,
                (array) config('lighthouse.namespaces.interfaces'),
                function (string $className): bool {
                    return method_exists($className, 'resolveType');
                }
            );

            $typeResolver = $interfaceClass
                ? [app($interfaceClass), 'resolveType']
                : static::typeResolverFallback();
        }

        return new InterfaceType([
            'name' => $nodeName,
            'description' => data_get($interfaceDefinition->description, 'value'),
            'fields' => $this->resolveFieldsFunction($interfaceDefinition),
            'resolveType' => $typeResolver,
        ]);
    }

    /**
     * Default type resolver for resolving interfaces or union types.
     *
     * We just assume that the rootValue that shall be returned from the
     * field is a class that is named just like the concrete Object Type
     * that is supposed to be returned.
     *
     * @return \Closure
     */
    public function typeResolverFallback(): Closure
    {
        return function ($rootValue): Type {
            return $this->get(
                class_basename($rootValue)
            );
        };
    }

    /**
     * @param  \GraphQL\Language\AST\UnionTypeDefinitionNode  $unionDefinition
     * @return \GraphQL\Type\Definition\UnionType
     */
    protected function resolveUnionType(UnionTypeDefinitionNode $unionDefinition): UnionType
    {
        $nodeName = $unionDefinition->name->value;

        if ($directive = ASTHelper::directiveDefinition($unionDefinition, 'union')) {
            $unionDirective = (new UnionDirective)->hydrate($unionDefinition);

            $typeResolver = $unionDirective->getResolverFromArgument('resolveType');
        } else {
            $unionClass = Utils::namespaceClassname(
                $nodeName,
                (array) config('lighthouse.namespaces.unions'),
                function (string $className): bool {
                    return method_exists($className, 'resolveType');
                }
            );

            $typeResolver = $unionClass
                ? [app($unionClass), 'resolveType']
                : static::typeResolverFallback();
        }

        return new UnionType([
            'name' => $nodeName,
            'description' => data_get($unionDefinition->description, 'value'),
            'types' => function () use ($unionDefinition): array {
                return (new Collection($unionDefinition->types))
                    ->map(function (NamedTypeNode $type): Type {
                        return $this->get(
                            $type->name->value
                        );
                    })
                    ->toArray();
            },
            'resolveType' => $typeResolver,
        ]);
    }
}
