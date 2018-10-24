<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\Type;
use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Language\AST\NamedTypeNode;
use Nuwave\Lighthouse\Support\Pipeline;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\InterfaceType;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Schema\DirectiveRegistry;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\AST\EnumValueDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Schema\Directives\Nodes\UnionDirective;
use Nuwave\Lighthouse\Schema\Directives\Nodes\InterfaceDirective;

class NodeFactory
{
    /** @var DirectiveRegistry */
    protected $directiveRegistry;
    /** @var TypeRegistry */
    protected $typeRegistry;
    /** @var Pipeline */
    protected $pipeline;
    /** @var ValueFactory */
    protected $valueFactory;
    /** @var FieldFactory */
    protected $fieldFactory;
    
    /**
     * @param DirectiveRegistry $directiveRegistry
     * @param TypeRegistry $typeRegistry
     * @param Pipeline $pipeline
     * @param ValueFactory $valueFactory
     * @param FieldFactory $fieldFactory
     */
    public function __construct(
        DirectiveRegistry $directiveRegistry,
        TypeRegistry $typeRegistry,
        Pipeline $pipeline,
        ValueFactory $valueFactory,
        FieldFactory $fieldFactory
    ) {
        $this->directiveRegistry = $directiveRegistry;
        $this->typeRegistry = $typeRegistry;
        $this->pipeline = $pipeline;
        $this->valueFactory = $valueFactory;
        $this->fieldFactory = $fieldFactory;
    }
    
    /**
     * Transform node to type.
     *
     * @param TypeDefinitionNode $definition
     *
     * @throws DirectiveException
     *
     * @return Type
     */
    public function handle(TypeDefinitionNode $definition): Type
    {
        $type = $this->hasTypeResolver($definition)
            ? $this->resolveTypeViaDirective($definition)
            : $this->resolveTypeDefault($definition);
    
        $nodeValue = $this->valueFactory->node($definition);
        $nodeValue->setType($type);
        
        return $this->pipeline
            ->send($nodeValue)
            ->through(
                $this->directiveRegistry->nodeMiddleware($definition)
            )
            ->via('handleNode')
            ->then(function (NodeValue $value) {
                return $value;
            })
            ->getType();
    }
    
    /**
     * Check if node has a type resolver directive.
     *
     * @param TypeDefinitionNode $definition
     *
     * @throws DirectiveException
     *
     * @return bool
     */
    protected function hasTypeResolver(TypeDefinitionNode $definition): bool
    {
        return $this->directiveRegistry->hasNodeResolver($definition);
    }
    
    /**
     * Use directive resolver to transform type.
     *
     * @param TypeDefinitionNode $definition
     *
     * @return Type
     * @throws DirectiveException
     */
    protected function resolveTypeViaDirective(TypeDefinitionNode $definition): Type
    {
        return $this->directiveRegistry
            ->nodeResolver($definition)
            ->resolveNode(
                $this->valueFactory->node($definition)
            );
    }
    
    /**
     * Transform value to type.
     *
     * @param Node $definition
     *
     * @throws DirectiveException
     *
     * @return Type
     */
    protected function resolveTypeDefault(Node $definition): Type
    {
        // Ignore TypeExtensionNode since they are merged before we get here
        switch (\get_class($definition)) {
            case EnumTypeDefinitionNode::class:
                return $this->resolveEnumType($definition);
            case ScalarTypeDefinitionNode::class:
                return $this->resolveScalarType($definition);
            case ObjectTypeDefinitionNode::class:
                return $this->resolveObjectType($definition);
            case InputObjectTypeDefinitionNode::class:
                return $this->resolveInputObjectType($definition);
            case InterfaceTypeDefinitionNode::class:
                return $this->resolveInterfaceType($definition);
            case UnionTypeDefinitionNode::class:
                return $this->resolveUnionType($definition);
            default:
                throw new InvariantViolation("Unknown type for Node [{$definition->name->value}]");
        }
    }
    
    /**
     * @param EnumTypeDefinitionNode $enumDefinition
     *
     * @return EnumType
     */
    protected function resolveEnumType(EnumTypeDefinitionNode $enumDefinition): EnumType
    {
        return new EnumType([
            'name' => $enumDefinition->name->value,
            'description' => data_get($enumDefinition->description, 'value'),
            'values' => collect($enumDefinition->values)
                ->mapWithKeys(function (EnumValueDefinitionNode $field) {
                    // Get the directive that is defined on the field itself
                    $directive = ASTHelper::directiveDefinition( $field, 'enum');

                    return [
                        $field->name->value => [
                            // If no explicit value is given, we default to the field name
                            'value' => $directive
                                ? ASTHelper::directiveArgValue($directive, 'value')
                                : $field->name->value,
                            'description' => data_get($field->description, 'value'),
                        ]
                    ];
                })
                ->toArray(),
        ]);
    }
    
    /**
     * @param ScalarTypeDefinitionNode $scalarDefinition
     *
     * @return ScalarType
     * @throws \Exception
     */
    protected function resolveScalarType(ScalarTypeDefinitionNode $scalarDefinition): ScalarType
    {
        $scalarName = $scalarDefinition->name->value;
        
        if($directive = ASTHelper::directiveDefinition($scalarDefinition, 'scalar')){
            $className = ASTHelper::directiveArgValue($directive, 'class');
        } else {
            $className = $scalarName;
        }

        $className = \namespace_classname($className, [
            config('lighthouse.namespaces.scalars')
        ]);

        if(!$className){
            throw new \Exception("No class found for the scalar {$scalarName}");
        }
        
        return new $className([
            'name' => $scalarName,
            'description' => data_get($scalarDefinition->description, 'value'),
        ]);
    }
    
    /**
     * @param ObjectTypeDefinitionNode $objectDefinition
     *
     * @return ObjectType
     */
    protected function resolveObjectType(ObjectTypeDefinitionNode $objectDefinition): ObjectType
    {
        return new ObjectType([
            'name' => $objectDefinition->name->value,
            'description' => data_get($objectDefinition->description, 'value'),
            'fields' => $this->resolveFieldsFunction($objectDefinition),
            'interfaces' => function () use ($objectDefinition) {
                return collect($objectDefinition->interfaces)
                    ->map(function (NamedTypeNode $interface) {
                        return $this->typeRegistry->get($interface->name->value);
                    })
                    ->toArray();
            },
        ]);
    }
    
    /**
     * @param InputObjectTypeDefinitionNode $inputDefinition
     *
     * @return InputObjectType
     */
    protected function resolveInputObjectType(InputObjectTypeDefinitionNode $inputDefinition): InputObjectType
    {
        return new InputObjectType([
            'name' => $inputDefinition->name->value,
            'description' => data_get($inputDefinition->description, 'value'),
            'fields' => $this->resolveInputFieldsFunction($inputDefinition),
        ]);
    }

    /**
     * @param InterfaceTypeDefinitionNode $interfaceDefinition
     *
     * @throws DirectiveException
     * @throws DefinitionException
     *
     * @return InterfaceType
     */
    protected function resolveInterfaceType(InterfaceTypeDefinitionNode $interfaceDefinition): InterfaceType
    {
        $nodeName = $interfaceDefinition->name->value;
        
        if($directive = ASTHelper::directiveDefinition($interfaceDefinition, 'interface')){
            $interfaceDirective = (new InterfaceDirective)->hydrate($interfaceDefinition);

            if($interfaceDirective->directiveHasArgument('resolveType')){
                $typeResolver = $interfaceDirective->getResolverFromArgument('resolveType');
            } else {
                /**
                 * @deprecated in v3 this will only be available as the argument resolveType
                 */
                $typeResolver = $interfaceDirective->getResolverFromArgument('resolver');
            }
        } else {
            $interfaceClass = \namespace_classname($nodeName, [
                config('lighthouse.namespaces.interfaces')
            ]);
        
            $typeResolver = \method_exists($interfaceClass, 'resolveType')
                ? [resolve($interfaceClass), 'resolveType']
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
     * @param UnionTypeDefinitionNode $unionDefinition
     *
     * @throws DirectiveException
     * @throws DefinitionException
     *
     * @return UnionType
     */
    protected function resolveUnionType(UnionTypeDefinitionNode $unionDefinition): UnionType
    {
        $nodeName = $unionDefinition->name->value;
        
        if($directive = ASTHelper::directiveDefinition($unionDefinition,'union')){
            $unionDirective = (new UnionDirective)->hydrate($unionDefinition);

            if($unionDirective->directiveHasArgument('resolveType')){
                $typeResolver = $unionDirective->getResolverFromArgument('resolveType');
            } else {
                /**
                 * @deprecated in v3 this will only be available as the argument resolveType
                 */
                $typeResolver = $unionDirective->getResolverFromArgument('resolver');
            }
        } else {
            $unionClass = \namespace_classname($nodeName, [
                config('lighthouse.namespaces.unions')
            ]);
            
            $typeResolver = \method_exists($unionClass, 'resolveType')
                ? [resolve($unionClass), 'resolveType']
                : static::typeResolverFallback();
        }
        
        return new UnionType([
            'name' => $nodeName,
            'description' => data_get($unionDefinition->description, 'value'),
            'types' => function () use ($unionDefinition) {
                return collect($unionDefinition->types)
                    ->map(function (NamedTypeNode $type) {
                        return $this->typeRegistry->get(
                            $type->name->value
                        );
                    })
                    ->toArray();
            },
            'resolveType' => $typeResolver,
        ]);
    }
    
    /**
     * If no type resolver is given, use this as a default.
     *
     * @return \Closure
     */
    public function typeResolverFallback(): \Closure
    {
        // The typeResolver receives only 3 arguments by `webonyx/graphql-php` instead of 4
        return function ($rootValue, $context, ResolveInfo $info){
            // Default to getting a type with the same name as the passed in root value
            // which is usually an Eloquent model
            return $this->typeRegistry->get(
                class_basename($rootValue)
            );
        };
    }
    
    /**
     * Returns a closure that lazy loads the fields for a constructed type.
     *
     * @param ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode $definition
     *
     * @return \Closure
     */
    protected function resolveFieldsFunction($definition): \Closure
    {
        return function() use ($definition){
            return collect($definition->fields)
                ->mapWithKeys(function (FieldDefinitionNode $fieldDefinition) use ($definition) {
                    $fieldValue = $this->valueFactory->field(
                        $this->valueFactory->node($definition),
                        $fieldDefinition
                    );
                    
                    return [
                        $fieldDefinition->name->value => $this->fieldFactory->handle($fieldValue),
                    ];
                })
                ->toArray();
        };
    }
    
    /**
     * Returns a closure that lazy loads the Input Fields for a constructed type.
     *
     * TODO https://github.com/nuwave/lighthouse/issues/184
     *
     * @param InputObjectTypeDefinitionNode $definition
     *
     * @return \Closure
     */
    protected function resolveInputFieldsFunction(InputObjectTypeDefinitionNode $definition): \Closure
    {
        return function() use ($definition){
            return collect($definition->fields)
                ->mapWithKeys(function (InputValueDefinitionNode $inputValueDefinition) use ($definition) {
                    $fieldValue = $this->valueFactory->field(
                        $this->valueFactory->node($definition),
                        $inputValueDefinition
                    );
                    
                    return [
                        $inputValueDefinition->name->value => $this->fieldFactory->handle($fieldValue),
                    ];
                })
                ->toArray();
        };
    }
}
