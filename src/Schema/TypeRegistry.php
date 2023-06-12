<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema;

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
use Illuminate\Container\Container;
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

/**
 * Holds programmatic type definitions.
 *
 * @api
 */
class TypeRegistry
{
    /** Lazily initialized. */
    protected FieldFactory $fieldFactory;

    /** Lazily initialized. */
    protected DocumentAST $documentAST;

    /**
     * Map from type names to resolved types.
     *
     * May contain `null` if a type was previously looked up and determined to not exist.
     * This allows short-circuiting repeated lookups for the same type.
     *
     * @var array<string, (\GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType)|null>
     */
    protected array $types = [];

    /**
     * Map from type names to lazily resolved types.
     *
     * @var array<string, callable(): \GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType>
     */
    protected array $lazyTypes = [];

    public function __construct(
        protected DirectiveLocator $directiveLocator,
        protected ArgumentFactory $argumentFactory,
    ) {}

    public function setDocumentAST(DocumentAST $documentAST): self
    {
        $this->documentAST = $documentAST;

        return $this;
    }

    public static function failedToLoadType(string $name): DefinitionException
    {
        return new DefinitionException("Failed to load type: {$name}. Make sure the type is present in your schema definition.");
    }

    public static function triedToRegisterPresentType(string $name): DefinitionException
    {
        return new DefinitionException("Tried to register a type that is already present in the schema: {$name}. Use overwrite() to ignore existing types.");
    }

    /** @param  array<string>  $possibleTypes */
    public static function unresolvableAbstractTypeMapping(string $fqcn, array $possibleTypes): DefinitionException
    {
        $ambiguousMapping = implode(', ', $possibleTypes);

        return new DefinitionException("Expected to map {$fqcn} to a single possible type, got: [{$ambiguousMapping}].");
    }

    /**
     * Get the given GraphQL type by name.
     *
     * @api
     *
     * @return \GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType
     */
    public function get(string $name): Type
    {
        return $this->search($name)
            ?? throw self::failedToLoadType($name);
    }

    /**
     * Search the given GraphQL type by name.
     *
     * @api
     *
     * @return (\GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType)|null
     */
    public function search(string $name): ?Type
    {
        if (isset($this->types[$name])) {
            return $this->types[$name];
        }

        if (isset($this->documentAST->types[$name])) {
            return $this->types[$name] = $this->handle($this->documentAST->types[$name]);
        }

        if (isset($this->lazyTypes[$name])) {
            return $this->types[$name] = $this->lazyTypes[$name]();
        }

        $standardTypes = Type::getStandardTypes();
        if (isset($standardTypes[$name])) {
            return $this->types[$name] = $standardTypes[$name];
        }

        return null;
    }

    /**
     * Is a type with the given name present?
     *
     * @api
     */
    public function has(string $name): bool
    {
        return $this->search($name) instanceof Type;
    }

    /**
     * Register an executable GraphQL type.
     *
     * @api
     *
     * @param  \GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType  $type
     */
    public function register(Type $type): self
    {
        $name = $type->name();
        if ($this->has($name)) {
            throw self::triedToRegisterPresentType($name);
        }

        $this->types[$name] = $type;

        return $this;
    }

    /**
     * Register an executable GraphQL type lazily.
     *
     * @api
     *
     * @param  callable(): \GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType  $type
     */
    public function registerLazy(string $name, callable $type): self
    {
        if ($this->has($name)) {
            throw self::triedToRegisterPresentType($name);
        }

        $this->lazyTypes[$name] = $type;

        return $this;
    }

    /**
     * Register a type, overwriting if it exists already.
     *
     * @api
     *
     * @param  \GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType  $type
     */
    public function overwrite(Type $type): self
    {
        $this->types[$type->name()] = $type;

        return $this;
    }

    /**
     * Register a type lazily, overwriting if it exists already.
     *
     * @api
     *
     * @param  callable(): \GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType  $type
     */
    public function overwriteLazy(string $name, callable $type): self
    {
        // The lazy type might have been resolved already
        unset($this->types[$name]);

        $this->lazyTypes[$name] = $type;

        return $this;
    }

    /**
     * Return all possible types that are registered.
     *
     * @return array<string, \GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType>
     */
    public function possibleTypes(): array
    {
        // Make sure all the types from the AST are eagerly converted
        // to find orphaned types, such as an object type that is only
        // ever used through its association to an interface.
        foreach ($this->documentAST->types as $typeDefinition) {
            $name = $typeDefinition->getName()->value;

            if (! isset($this->types[$name])) {
                $this->types[$name] = $this->handle($typeDefinition);
            }
        }

        foreach ($this->lazyTypes as $name => $lazyType) {
            if (! isset($this->types[$name])) {
                $this->types[$name] = $lazyType();
            }
        }

        return array_filter($this->types);
    }

    /**
     * Get the types that are currently resolved.
     *
     * This does not return all possible types, only those that
     * are programmatically registered or already resolved.
     *
     * @return array<string, \GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType>
     */
    public function resolvedTypes(): array
    {
        return array_filter($this->types);
    }

    /**
     * Transform a definition node to an executable type.
     *
     * Only public for testing.
     *
     * @param  \GraphQL\Language\AST\TypeDefinitionNode&\GraphQL\Language\AST\Node  $definition
     *
     * @return \GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType
     */
    public function handle(TypeDefinitionNode $definition): Type
    {
        $typeValue = new TypeValue($definition);
        $typeMiddlewareDirectives = $this->directiveLocator
            ->associatedOfType($definition, TypeMiddleware::class)
            ->all();
        foreach ($typeMiddlewareDirectives as $typeMiddlewareDirective) {
            assert($typeMiddlewareDirective instanceof TypeMiddleware);
            $typeMiddlewareDirective->handleNode($typeValue);
        }

        $typeResolver = $this->directiveLocator->exclusiveOfType($definition, TypeResolver::class);
        if ($typeResolver instanceof TypeResolver) {
            return $typeResolver->resolveNode($typeValue);
        }

        return $this->resolveType($definition);
    }

    /**
     * The default type transformations.
     *
     * @param  \GraphQL\Language\AST\TypeDefinitionNode&\GraphQL\Language\AST\Node  $typeDefinition
     *
     * @return \GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType
     */
    protected function resolveType(TypeDefinitionNode $typeDefinition): Type
    {
        return match (true) {
            $typeDefinition instanceof EnumTypeDefinitionNode => $this->resolveEnumType($typeDefinition),
            $typeDefinition instanceof ScalarTypeDefinitionNode => $this->resolveScalarType($typeDefinition),
            $typeDefinition instanceof ObjectTypeDefinitionNode => $this->resolveObjectType($typeDefinition),
            $typeDefinition instanceof InputObjectTypeDefinitionNode => $this->resolveInputObjectType($typeDefinition),
            $typeDefinition instanceof InterfaceTypeDefinitionNode => $this->resolveInterfaceType($typeDefinition),
            $typeDefinition instanceof UnionTypeDefinitionNode => $this->resolveUnionType($typeDefinition),
            default => throw new InvariantViolation("Unknown type for definition {$typeDefinition->getName()->value}."),
        };
    }

    protected function resolveEnumType(EnumTypeDefinitionNode $enumDefinition): EnumType
    {
        /** @var array<string, array<string, mixed>> $values */
        $values = [];

        foreach ($enumDefinition->values as $enumValue) {
            $enumDirective = $this->directiveLocator->exclusiveOfType($enumValue, EnumDirective::class);

            $values[$enumValue->name->value] = [
                // If no explicit value is given, we default to the name of the value
                'value' => $enumDirective instanceof EnumDirective
                    ? $enumDirective->value()
                    : $enumValue->name->value,
                'description' => $enumValue->description->value ?? null,
                'deprecationReason' => ASTHelper::deprecationReason($enumValue),
            ];
        }

        return new EnumType([
            'name' => $enumDefinition->name->value,
            'description' => $enumDefinition->description->value ?? null,
            'values' => $values,
            'astNode' => $enumDefinition,
        ]);
    }

    protected function resolveScalarType(ScalarTypeDefinitionNode $scalarDefinition): ScalarType
    {
        $scalarName = $scalarDefinition->name->value;

        $scalarDirective = ASTHelper::directiveDefinition($scalarDefinition, 'scalar');
        $className = $scalarDirective === null
            ? $scalarName
            : ASTHelper::directiveArgValue($scalarDirective, 'class');

        $namespacesToTry = (array) config('lighthouse.namespaces.scalars');

        $namespacedClassName = Utils::namespaceClassname(
            $className,
            $namespacesToTry,
            static fn (string $className): bool => is_subclass_of($className, ScalarType::class),
        );
        assert(is_null($namespacedClassName) || is_subclass_of($namespacedClassName, ScalarType::class));

        if ($namespacedClassName === null) {
            $scalarClass = ScalarType::class;
            $consideredNamespaces = implode(', ', $namespacesToTry);
            throw new DefinitionException("Failed to find class {$className} extends {$scalarClass} in namespaces [{$consideredNamespaces}] for the scalar {$scalarName}.");
        }

        return new $namespacedClassName([
            'name' => $scalarName,
            'description' => $scalarDefinition->description->value ?? null,
            'astNode' => $scalarDefinition,
        ]);
    }

    protected function resolveObjectType(ObjectTypeDefinitionNode $objectDefinition): ObjectType
    {
        return new ObjectType([
            'name' => $objectDefinition->name->value,
            'description' => $objectDefinition->description->value ?? null,
            'fields' => $this->makeFieldsLoader($objectDefinition),
            'interfaces' => function () use ($objectDefinition): array {
                $interfaces = [];

                foreach ($objectDefinition->interfaces as $interface) {
                    $interfaces[] = $this->get($interface->name->value);
                }

                /** @var list<\GraphQL\Type\Definition\InterfaceType> $interfaces */
                return $interfaces;
            },
            'astNode' => $objectDefinition,
        ]);
    }

    /**
     * Returns a closure that lazy loads the fields for a constructed type.
     *
     * @return \Closure(): array<string, \Closure(): array<string, mixed>>
     */
    protected function makeFieldsLoader(ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode $typeDefinition): \Closure
    {
        return function () use ($typeDefinition): array {
            $fieldFactory = $this->fieldFactory();
            $typeValue = new TypeValue($typeDefinition);
            $fields = [];

            foreach ($typeDefinition->fields as $fieldDefinition) {
                $fields[$fieldDefinition->name->value] = static fn (): array => $fieldFactory->handle(
                    new FieldValue($typeValue, $fieldDefinition),
                );
            }

            return $fields;
        };
    }

    protected function resolveInputObjectType(InputObjectTypeDefinitionNode $inputDefinition): InputObjectType
    {
        /**
         * @return array<string, array<string, mixed>>
         */
        $fields = fn (): array => $this->argumentFactory->toTypeMap($inputDefinition->fields);

        return new InputObjectType([
            'name' => $inputDefinition->name->value,
            'description' => $inputDefinition->description->value ?? null,
            'fields' => $fields,
            'astNode' => $inputDefinition,
        ]);
    }

    protected function resolveInterfaceType(InterfaceTypeDefinitionNode $interfaceDefinition): InterfaceType
    {
        $nodeName = $interfaceDefinition->name->value;

        if (($directiveNode = ASTHelper::directiveDefinition($interfaceDefinition, 'interface')) !== null) {
            $interfaceDirective = (new InterfaceDirective())->hydrate($directiveNode, $interfaceDefinition);

            $typeResolver = $interfaceDirective->getResolverFromArgument('resolveType');
        } else {
            $typeResolver
                = $this->typeResolverFromClass(
                    $nodeName,
                    (array) config('lighthouse.namespaces.interfaces'),
                )
                ?: $this->typeResolverFallback(
                    $this->possibleImplementations($interfaceDefinition),
                );
        }

        return new InterfaceType([
            'name' => $nodeName,
            'description' => $interfaceDefinition->description->value ?? null,
            'fields' => $this->makeFieldsLoader($interfaceDefinition),
            'resolveType' => $typeResolver,
            'astNode' => $interfaceDefinition,
            'interfaces' => function () use ($interfaceDefinition): array {
                $interfaces = [];

                foreach ($interfaceDefinition->interfaces as $interface) {
                    $interfaces[] = $this->get($interface->name->value);
                }

                /** @var list<\GraphQL\Type\Definition\InterfaceType> $interfaces */
                return $interfaces;
            },
        ]);
    }

    /** @return list<string> */
    protected function possibleImplementations(InterfaceTypeDefinitionNode $interfaceTypeDefinitionNode): array
    {
        $name = $interfaceTypeDefinitionNode->name->value;

        /** @var list<string> $implementations */
        $implementations = [];

        foreach ($this->documentAST->types as $typeDefinition) {
            if (
                $typeDefinition instanceof ObjectTypeDefinitionNode
                && ASTHelper::typeImplementsInterface($typeDefinition, $name)
            ) {
                $implementations[] = $typeDefinition->name->value;
            }
        }

        return $implementations;
    }

    /** @param  array<string>  $namespaces */
    protected function typeResolverFromClass(string $nodeName, array $namespaces): ?\Closure
    {
        $className = Utils::namespaceClassname(
            $nodeName,
            $namespaces,
            static fn (string $className): bool => method_exists($className, '__invoke'),
        );

        if ($className !== null) {
            $typeResolver = Container::getInstance()->make($className);
            assert(is_object($typeResolver));

            return \Closure::fromCallable([$typeResolver, '__invoke']);
        }

        return null;
    }

    /**
     * Default type resolver for resolving interfaces or union types.
     *
     * @param  list<string>  $possibleTypes
     *
     * @return \Closure(mixed): Type
     */
    protected function typeResolverFallback(array $possibleTypes): \Closure
    {
        return function ($root) use ($possibleTypes): Type {
            $explicitTypename = data_get($root, '__typename');
            if ($explicitTypename !== null) {
                return $this->get($explicitTypename);
            }

            if (is_object($root)) {
                $fqcn = $root::class;
                $explicitSchemaMapping = $this->documentAST->classNameToObjectTypeNames[$fqcn] ?? null;
                if ($explicitSchemaMapping !== null) {
                    $actuallyPossibleTypes = array_intersect($possibleTypes, $explicitSchemaMapping);

                    if (count($actuallyPossibleTypes) !== 1) {
                        throw self::unresolvableAbstractTypeMapping($fqcn, $actuallyPossibleTypes);
                    }

                    return $this->get(end($actuallyPossibleTypes));
                }

                return $this->get(class_basename($root));
            }

            return $this->get($root);
        };
    }

    protected function resolveUnionType(UnionTypeDefinitionNode $unionDefinition): UnionType
    {
        $nodeName = $unionDefinition->name->value;

        if (($directiveNode = ASTHelper::directiveDefinition($unionDefinition, 'union')) !== null) {
            $unionDirective = (new UnionDirective())->hydrate($directiveNode, $unionDefinition);

            $typeResolver = $unionDirective->getResolverFromArgument('resolveType');
        } else {
            $typeResolver = $this->typeResolverFromClass(
                $nodeName,
                (array) config('lighthouse.namespaces.unions'),
            )
                ?: $this->typeResolverFallback(
                    $this->possibleUnionTypes($unionDefinition),
                );
        }

        return new UnionType([
            'name' => $nodeName,
            'description' => $unionDefinition->description->value ?? null,
            'types' => function () use ($unionDefinition): array {
                $types = [];

                foreach ($unionDefinition->types as $type) {
                    $types[] = $this->get($type->name->value);
                }

                /** @var list<\GraphQL\Type\Definition\ObjectType> $types */
                return $types;
            },
            'resolveType' => $typeResolver,
            'astNode' => $unionDefinition,
        ]);
    }

    protected function fieldFactory(): FieldFactory
    {
        return $this->fieldFactory
            ??= Container::getInstance()->make(FieldFactory::class);
    }

    /** @return list<string> */
    protected function possibleUnionTypes(UnionTypeDefinitionNode $unionDefinition): array
    {
        $types = [];
        foreach ($unionDefinition->types as $type) {
            $types[] = $type->name->value;
        }

        return $types;
    }
}
