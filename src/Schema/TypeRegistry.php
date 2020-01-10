<?php

namespace Nuwave\Lighthouse\Schema;

use Closure;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\EnumDirective;
use Nuwave\Lighthouse\Schema\Directives\InterfaceDirective;
use Nuwave\Lighthouse\Schema\Directives\UnionDirective;
use Nuwave\Lighthouse\Schema\Factories\ArgumentFactory;
use Nuwave\Lighthouse\Schema\Factories\DirectiveFactory;
use Nuwave\Lighthouse\Schema\Factories\FieldFactory;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Values\TypeValue;
use Nuwave\Lighthouse\Support\Contracts\TypeMiddleware;
use Nuwave\Lighthouse\Support\Contracts\TypeResolver;
use Nuwave\Lighthouse\Support\Pipeline;
use Nuwave\Lighthouse\Support\Utils;

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
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    public function get(string $name): Type
    {
        if (! isset($this->types[$name])) {
            $typeDefinition = $this->documentAST->types[$name] ?? null;
            if (! $typeDefinition) {
                throw new DefinitionException(<<<EOL
Lighthouse failed while trying to load a type: $name

Make sure the type is present in your schema definition.

EOL
                );
            }

            $this->types[$name] = $this->handle($typeDefinition);
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
        // to find orphaned types, such as an object type that is only
        // ever used through its association to an interface
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
                if ($typeResolver = $this->directiveFactory->createSingleDirectiveOfType($definition, TypeResolver::class)) {
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
            // Ignore TypeExtensionNode since they are merged before we get here
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
        $values = [];
        foreach ($enumDefinition->values as $enumValue) {
            /** @var \Nuwave\Lighthouse\Schema\Directives\EnumDirective|null $enumDirective */
            $enumDirective = $this->directiveFactory->createSingleDirectiveOfType($enumValue, EnumDirective::class);

            $values[$enumValue->name->value] = [
                // If no explicit value is given, we default to the name of the value
                'value' => $enumDirective
                    ? $enumDirective->value()
                    : $enumValue->name->value,
                'description' => data_get($enumValue->description, 'value'),
            ];
        }

        return new EnumType([
            'name' => $enumDefinition->name->value,
            'description' => data_get($enumDefinition->description, 'value'),
            'values' => $values,
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
                $interfaces = [];

                // Might be a NodeList, so we can not use array_map()
                foreach ($objectDefinition->interfaces as $interface) {
                    $interfaces [] = $this->get($interface->name->value);
                }

                return $interfaces;
            },
        ]);
    }

    /**
     * Returns a closure that lazy loads the fields for a constructed type.
     *
     * @param  \GraphQL\Language\AST\ObjectTypeDefinitionNode|\GraphQL\Language\AST\InterfaceTypeDefinitionNode  $typeDefinition
     * @return \Closure
     */
    protected function resolveFieldsFunction($typeDefinition): Closure
    {
        return function () use ($typeDefinition): array {
            $typeValue = new TypeValue($typeDefinition);
            $fields = [];

            // Might be a NodeList, so we can not use array_map()
            foreach ($typeDefinition->fields as $fieldDefinition) {
                /** @var \Nuwave\Lighthouse\Schema\Factories\FieldFactory $fieldFactory */
                $fieldFactory = app(FieldFactory::class);
                $fieldValue = new FieldValue($typeValue, $fieldDefinition);

                $fields[$fieldDefinition->name->value] = $fieldFactory->handle($fieldValue);
            }

            return $fields;
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
                return $this->argumentFactory->toTypeMap($inputDefinition->fields);
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

        if ($directiveNode = ASTHelper::directiveDefinition($interfaceDefinition, 'interface')) {
            $interfaceDirective = (new InterfaceDirective)->hydrate($directiveNode, $interfaceDefinition);

            $typeResolver = $interfaceDirective->getResolverFromArgument('resolveType');
        } else {
            $typeResolver =
                $this->findTypeResolverClass(
                    $nodeName,
                    (array) config('lighthouse.namespaces.interfaces')
                )
                ?: static::typeResolverFallback();
        }

        return new InterfaceType([
            'name' => $nodeName,
            'description' => data_get($interfaceDefinition->description, 'value'),
            'fields' => $this->resolveFieldsFunction($interfaceDefinition),
            'resolveType' => $typeResolver,
        ]);
    }

    protected function findTypeResolverClass(string $nodeName, array $namespaces): ?Closure
    {
        // TODO use only __invoke in v5
        $className = Utils::namespaceClassname(
            $nodeName,
            $namespaces,
            function (string $className): bool {
                return method_exists($className, 'resolveType');
            }
        );
        if ($className) {
            return Closure::fromCallable(
                [app($className), 'resolveType']
            );
        }

        $className = Utils::namespaceClassname(
            $nodeName,
            $namespaces,
            function (string $className): bool {
                return method_exists($className, '__invoke');
            }
        );
        if ($className) {
            return Closure::fromCallable(
                [app($className), '__invoke']
            );
        }

        return null;
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
            return $this->get(class_basename($rootValue));
        };
    }

    /**
     * @param  \GraphQL\Language\AST\UnionTypeDefinitionNode  $unionDefinition
     * @return \GraphQL\Type\Definition\UnionType
     */
    protected function resolveUnionType(UnionTypeDefinitionNode $unionDefinition): UnionType
    {
        $nodeName = $unionDefinition->name->value;

        if ($directiveNode = ASTHelper::directiveDefinition($unionDefinition, 'union')) {
            $unionDirective = (new UnionDirective)->hydrate($directiveNode, $unionDefinition);

            $typeResolver = $unionDirective->getResolverFromArgument('resolveType');
        } else {
            $typeResolver =
                $this->findTypeResolverClass(
                    $nodeName,
                    (array) config('lighthouse.namespaces.unions')
                )
                ?: static::typeResolverFallback();
        }

        return new UnionType([
            'name' => $nodeName,
            'description' => data_get($unionDefinition->description, 'value'),
            'types' => function () use ($unionDefinition): array {
                $types = [];

                // Might be a NodeList, so we can not use array_map()
                foreach ($unionDefinition->types as $type) {
                    $types[] = $this->get($type->name->value);
                }

                return $types;
            },
            'resolveType' => $typeResolver,
        ]);
    }
}
