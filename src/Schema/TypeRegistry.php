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
use Illuminate\Pipeline\Pipeline;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\EnumDirective;
use Nuwave\Lighthouse\Schema\Directives\InterfaceDirective;
use Nuwave\Lighthouse\Schema\Directives\UnionDirective;
use Nuwave\Lighthouse\Schema\Factories\ArgumentFactory;
use Nuwave\Lighthouse\Schema\Factories\FieldFactory;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Values\TypeValue;
use Nuwave\Lighthouse\Support\Contracts\TypeMiddleware;
use Nuwave\Lighthouse\Support\Contracts\TypeResolver;
use Nuwave\Lighthouse\Support\Utils;

class TypeRegistry
{
    /**
     * @var \Illuminate\Pipeline\Pipeline
     */
    protected $pipeline;

    /**
     * @var \Nuwave\Lighthouse\Schema\DirectiveLocator
     */
    protected $directiveLocator;

    /**
     * @var \Nuwave\Lighthouse\Schema\Factories\ArgumentFactory
     */
    protected $argumentFactory;

    /**
     * Lazily initialized.
     *
     * @var \Nuwave\Lighthouse\Schema\Factories\FieldFactory
     */
    protected $fieldFactory;

    /**
     * Lazily initialized.
     *
     * @var \Nuwave\Lighthouse\Schema\AST\DocumentAST
     */
    protected $documentAST;

    /**
     * Map from type names to resolved types.
     *
     * @var array<string, \GraphQL\Type\Definition\Type>
     */
    protected $types = [];

    public function __construct(
        Pipeline $pipeline,
        DirectiveLocator $directiveLocator,
        ArgumentFactory $argumentFactory
    ) {
        $this->pipeline = $pipeline;
        $this->directiveLocator = $directiveLocator;
        $this->argumentFactory = $argumentFactory;
    }

    /**
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
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    public function get(string $name): Type
    {
        if (! $this->has($name)) {
            throw new DefinitionException(<<<EOL
Lighthouse failed while trying to load a type: $name

Make sure the type is present in your schema definition.

EOL
            );
        }

        return $this->types[$name];
    }

    /**
     * Is a type with the given name present?
     */
    public function has(string $name): bool
    {
        return isset($this->types[$name])
            || $this->fromAST($name) instanceof Type;
    }

    /**
     * Register an executable GraphQL type.
     *
     * @return $this
     */
    public function register(Type $type): self
    {
        $name = $type->name;
        if ($this->has($name)) {
            throw new DefinitionException("Tried to register a type that is already present in the schema: {$name}. Use overwrite() to ignore existing types.");
        }

        $this->types[$name] = $type;

        return $this;
    }

    /**
     * Register a type, overwriting if it exists already.
     *
     * @return $this
     */
    public function overwrite(Type $type): self
    {
        $this->types[$type->name] = $type;

        return $this;
    }

    /**
     * Attempt to make a type of the given name from the AST.
     */
    protected function fromAST(string $name): ?Type
    {
        $typeDefinition = $this->documentAST->types[$name] ?? null;
        if ($typeDefinition === null) {
            return null;
        }

        return $this->types[$name] = $this->handle($typeDefinition);
    }

    /**
     * Return all possible types that are registered.
     *
     * @return array<string, \GraphQL\Type\Definition\Type>
     */
    public function possibleTypes(): array
    {
        // Make sure all the types from the AST are eagerly converted
        // to find orphaned types, such as an object type that is only
        // ever used through its association to an interface
        foreach ($this->documentAST->types as $typeDefinition) {
            $name = $typeDefinition->name->value;

            if (! isset($this->types[$name])) {
                $this->types[$name] = $this->handle($typeDefinition);
            }
        }

        return $this->types;
    }

    /**
     * Get the types that are currently resolved.
     *
     * Note that this does not all possible types, only those that
     * are programmatically registered or already resolved.
     *
     * @return array<string, \GraphQL\Type\Definition\Type>
     */
    public function resolvedTypes(): array
    {
        return $this->types;
    }

    /**
     * Transform a definition node to an executable type.
     *
     * @param  \GraphQL\Language\AST\TypeDefinitionNode&\GraphQL\Language\AST\Node $definition
     */
    public function handle(TypeDefinitionNode $definition): Type
    {
        return $this->pipeline
            ->send(
                new TypeValue($definition)
            )
            ->through(
                $this->directiveLocator
                    ->associatedOfType($definition, TypeMiddleware::class)
                    ->all()
            )
            ->via('handleNode')
            ->then(function (TypeValue $value) use ($definition): Type {
                $typeResolver = $this->directiveLocator->exclusiveOfType($definition, TypeResolver::class);
                if ($typeResolver !== null) {
                    /** @var \Nuwave\Lighthouse\Support\Contracts\TypeResolver $typeResolver */
                    return $typeResolver->resolveNode($value);
                }

                return $this->resolveType($definition);
            });
    }

    /**
     * The default type transformations.
     *
     * @param  \GraphQL\Language\AST\TypeDefinitionNode&\GraphQL\Language\AST\Node $typeDefinition
     *
     * @throws \GraphQL\Error\InvariantViolation
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
            default:
                throw new InvariantViolation(
                    "Unknown type for definition [{$typeDefinition->name->value}]"
                );
        }
    }

    protected function resolveEnumType(EnumTypeDefinitionNode $enumDefinition): EnumType
    {
        /** @var array<string, array<string, mixed>> $values */
        $values = [];

        foreach ($enumDefinition->values as $enumValue) {
            /** @var \Nuwave\Lighthouse\Schema\Directives\EnumDirective|null $enumDirective */
            $enumDirective = $this->directiveLocator->exclusiveOfType($enumValue, EnumDirective::class);

            $values[$enumValue->name->value] = [
                // If no explicit value is given, we default to the name of the value
                'value' => $enumDirective !== null
                    ? $enumDirective->value()
                    : $enumValue->name->value,
                'description' => data_get($enumValue->description, 'value'),
                'deprecationReason' => ASTHelper::deprecationReason($enumValue),
            ];
        }

        return new EnumType([
            'name' => $enumDefinition->name->value,
            'description' => data_get($enumDefinition->description, 'value'),
            'values' => $values,
            'astNode' => $enumDefinition,
        ]);
    }

    /**
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    protected function resolveScalarType(ScalarTypeDefinitionNode $scalarDefinition): ScalarType
    {
        $scalarName = $scalarDefinition->name->value;

        if (($directive = ASTHelper::directiveDefinition($scalarDefinition, 'scalar')) !== null) {
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
                "No matching subclass of GraphQL\Type\Definition\ScalarType found for the scalar {$scalarName}"
            );
        }

        return new $className([
            'name' => $scalarName,
            'description' => data_get($scalarDefinition->description, 'value'),
            'astNode' => $scalarDefinition,
        ]);
    }

    protected function resolveObjectType(ObjectTypeDefinitionNode $objectDefinition): ObjectType
    {
        return new ObjectType([
            'name' => $objectDefinition->name->value,
            'description' => data_get($objectDefinition->description, 'value'),
            'fields' => $this->makeFieldsLoader($objectDefinition),
            'interfaces' =>
                /**
                 * @return list<\GraphQL\Type\Definition\Type>
                 */
                function () use ($objectDefinition): array {
                    $interfaces = [];

                    // Might be a NodeList, so we can not use array_map()
                    foreach ($objectDefinition->interfaces as $interface) {
                        $interfaces [] = $this->get($interface->name->value);
                    }

                    return $interfaces;
                },
            'astNode' => $objectDefinition,
        ]);
    }

    /**
     * Returns a closure that lazy loads the fields for a constructed type.
     *
     * @param  \GraphQL\Language\AST\ObjectTypeDefinitionNode|\GraphQL\Language\AST\InterfaceTypeDefinitionNode  $typeDefinition
     *
     * @return \Closure(): array<string, Closure(): array<string, mixed>>
     */
    protected function makeFieldsLoader($typeDefinition): Closure
    {
        return
            /**
             * @return array<string, Closure(): array<string, mixed>>
             */
            function () use ($typeDefinition): array {
                $fieldFactory = $this->fieldFactory();
                $typeValue = new TypeValue($typeDefinition);
                $fields = [];

                foreach ($typeDefinition->fields as $fieldDefinition) {
                    $fields[$fieldDefinition->name->value] = static function () use ($fieldFactory, $typeValue, $fieldDefinition): array {
                        return $fieldFactory->handle(
                            new FieldValue($typeValue, $fieldDefinition)
                        );
                    };
                }

                return $fields;
            };
    }

    protected function resolveInputObjectType(InputObjectTypeDefinitionNode $inputDefinition): InputObjectType
    {
        return new InputObjectType([
            'name' => $inputDefinition->name->value,
            'description' => data_get($inputDefinition->description, 'value'),
            'fields' =>
                /**
                 * @return array<string, array<string, mixed>>
                 */
                function () use ($inputDefinition): array {
                    return $this->argumentFactory->toTypeMap($inputDefinition->fields);
                },
            'astNode' => $inputDefinition,
        ]);
    }

    protected function resolveInterfaceType(InterfaceTypeDefinitionNode $interfaceDefinition): InterfaceType
    {
        $nodeName = $interfaceDefinition->name->value;

        if (($directiveNode = ASTHelper::directiveDefinition($interfaceDefinition, 'interface')) !== null) {
            $interfaceDirective = (new InterfaceDirective)->hydrate($directiveNode, $interfaceDefinition);

            $typeResolver = $interfaceDirective->getResolverFromArgument('resolveType');
        } else {
            $typeResolver =
                $this->typeResolverFromClass(
                    $nodeName,
                    (array) config('lighthouse.namespaces.interfaces')
                )
                ?: $this->typeResolverFallback();
        }

        return new InterfaceType([
            'name' => $nodeName,
            'description' => data_get($interfaceDefinition->description, 'value'),
            'fields' => $this->makeFieldsLoader($interfaceDefinition),
            'resolveType' => $typeResolver,
            'astNode' => $interfaceDefinition,
        ]);
    }

    /**
     * @param  array<string>  $namespaces
     */
    protected function typeResolverFromClass(string $nodeName, array $namespaces): ?Closure
    {
        $className = Utils::namespaceClassname(
            $nodeName,
            $namespaces,
            function (string $className): bool {
                return method_exists($className, '__invoke');
            }
        );

        if ($className) {
            return Closure::fromCallable(
                // @phpstan-ignore-next-line this works
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
     * @return Closure(mixed): Type
     */
    protected function typeResolverFallback(): Closure
    {
        return function ($root): Type {
            if (is_array($root) && isset($root['__typename'])) {
                $name = $root['__typename'];
            } else {
                $name = class_basename($root);
            }

            return $this->get($name);
        };
    }

    protected function resolveUnionType(UnionTypeDefinitionNode $unionDefinition): UnionType
    {
        $nodeName = $unionDefinition->name->value;

        if (($directiveNode = ASTHelper::directiveDefinition($unionDefinition, 'union')) !== null) {
            $unionDirective = (new UnionDirective)->hydrate($directiveNode, $unionDefinition);

            $typeResolver = $unionDirective->getResolverFromArgument('resolveType');
        } else {
            $typeResolver =
                $this->typeResolverFromClass(
                    $nodeName,
                    (array) config('lighthouse.namespaces.unions')
                )
                ?: $this->typeResolverFallback();
        }

        return new UnionType([
            'name' => $nodeName,
            'description' => data_get($unionDefinition->description, 'value'),
            'types' =>
                /**
                 * @return list<\GraphQL\Type\Definition\Type>
                 */
                function () use ($unionDefinition): array {
                    $types = [];

                    foreach ($unionDefinition->types as $type) {
                        $types[] = $this->get($type->name->value);
                    }

                    return $types;
                },
            'resolveType' => $typeResolver,
            'astNode' => $unionDefinition,
        ]);
    }

    protected function fieldFactory(): FieldFactory
    {
        if (! isset($this->fieldFactory)) {
            $this->fieldFactory = app(FieldFactory::class);
        }

        return $this->fieldFactory;
    }
}
