<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use Nuwave\Lighthouse\Support\Pipeline;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\InterfaceType;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use GraphQL\Type\Definition\InputObjectType;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Schema\DirectiveRegistry;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\AST\EnumValueDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
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
     * @param NodeValue $value
     *
     * @throws \Exception
     *
     * @return Type
     */
    public function handle(NodeValue $value): Type
    {
        $value->setType(
            $this->hasTypeResolver($value)
                ? $this->resolveTypeViaDirective($value)
                : $this->resolveTypeDefault($value)
        );

        return $this->applyMiddleware($value)->getType();
    }

    /**
     * Check if node has a type resolver directive.
     *
     * @param NodeValue $value
     *
     * @return bool
     */
    protected function hasTypeResolver(NodeValue $value): bool
    {
        return $this->directiveRegistry->hasNodeResolver($value->getNode());
    }
    
    /**
     * Use directive resolver to transform type.
     *
     * @param NodeValue $value
     *
     * @throws DirectiveException
     *
     * @return Type
     */
    protected function resolveTypeViaDirective(NodeValue $value): Type
    {
        return $this->directiveRegistry
            ->nodeResolver($value->getNode())
            ->resolveNode($value);
    }

    /**
     * Transform value to type.
     *
     * @param NodeValue $value
     *
     * @throws \Exception
     *
     * @return Type
     */
    protected function resolveTypeDefault(NodeValue $value): Type
    {
        // Ignore TypeExtensionNode since they are merged before we get here
        switch (\get_class($value->getNode())) {
            case EnumTypeDefinitionNode::class:
                return $this->resolveEnumType($value);
            case ScalarTypeDefinitionNode::class:
                return $this->resolveScalarType($value);
            case ObjectTypeDefinitionNode::class:
                return $this->resolveObjectType($value);
            case InputObjectTypeDefinitionNode::class:
                return $this->resolveInputObjectType($value);
            case InterfaceTypeDefinitionNode::class:
                return $this->resolveInterfaceType($value);
            case UnionTypeDefinitionNode::class:
                return $this->resolveUnionType($value);
            default:
                throw new \Exception("Unknown type for Node [{$value->getNodeName()}]");
        }
    }

    /**
     * @param NodeValue $enumNodeValue
     *
     * @return EnumType
     */
    protected function resolveEnumType(NodeValue $enumNodeValue): EnumType
    {
        return new EnumType([
            'name' => $enumNodeValue->getNodeName(),
            'values' => collect($enumNodeValue->getNode()->values)
                ->mapWithKeys(function (EnumValueDefinitionNode $field) {
                    // Get the directive that is defined on the field itself
                    $directive = ASTHelper::directiveDefinition( 'enum', $field);
                
                    if (!$directive) {
                        return [];
                    }
                
                    return [
                        $field->name->value => [
                            'value' => ASTHelper::directiveArgValue($directive, 'value'),
                            'description' => $field->description
                        ]
                    ];
                })->toArray(),
        ]);
    }
    
    /**
     * @param NodeValue $scalarNodeValue
     *
     * @throws \Exception
     *
     * @return ScalarType
     */
    protected function resolveScalarType(NodeValue $scalarNodeValue): ScalarType
    {
        $nodeName = $scalarNodeValue->getNodeName();
        $className = \namespace_classname($nodeName, [
            config('lighthouse.namespaces.scalars')
        ]);
        
        if(!$className){
            throw new \Exception("No class found for the scalar {$nodeName}");
        }
        
        return new $className([
            'name' => $nodeName,
            'description' => $scalarNodeValue->getNode()->description,
        ]);
    }
    
    /**
     * @param NodeValue $value
     *
     * @return ObjectType
     */
    protected function resolveObjectType(NodeValue $value): ObjectType
    {
        return new ObjectType([
            'name' => $value->getNodeName(),
            'description' => $value->getNode()->description,
            'fields' => $this->resolveFieldsFunction($value),
            'interfaces' => function () use ($value) {
                return $value->getInterfaceNames()
                    ->map(function ($interfaceName) {
                        return $this->typeRegistry->get($interfaceName);
                    })
                    ->toArray();
            },
        ]);
    }

    /**
     * @param NodeValue $value
     *
     * @return InputObjectType
     */
    protected function resolveInputObjectType(NodeValue $value): InputObjectType
    {
        return new InputObjectType([
            'name' => $value->getNodeName(),
            'description' => $value->getNode()->description,
            'fields' => $this->resolveFieldsFunction($value),
        ]);
    }
    
    /**
     * @param NodeValue $interfaceNodeValue
     *
     * @throws DirectiveException
     *
     * @return InterfaceType
     */
    protected function resolveInterfaceType(NodeValue $interfaceNodeValue): InterfaceType
    {
        $nodeName = $interfaceNodeValue->getNodeName();
    
        $interfaceDefinition = $interfaceNodeValue->getNode();
        if($directive = ASTHelper::directiveDefinition('interface', $interfaceDefinition)){
            $typeResolver = (new InterfaceDirective)->hydrate($interfaceDefinition)->getResolver();
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
            'description' => $interfaceNodeValue->getNode()->description,
            'fields' => $this->resolveFieldsFunction($interfaceNodeValue),
            'resolveType' => $typeResolver,
        ]);
    }
    
    /**
     * @param NodeValue $value
     *
     * @throws DirectiveException
     *
     * @return UnionType
     */
    protected function resolveUnionType(NodeValue $value): UnionType
    {
        $nodeName = $value->getNodeName();
    
        $unionDefinition = $value->getNode();
        if($directive = ASTHelper::directiveDefinition('union', $unionDefinition)){
            $typeResolver = (new UnionDirective)->hydrate($unionDefinition)->getResolver();
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
            'description' => $value->getNode()->description,
            'types' => function () use ($value) {
                return collect($value->getNode()->types)
                    ->map(function ($type) {
                        return $this->typeRegistry->get($type->name->value);
                    })
                    ->filter()
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
            return $this->typeRegistry->get(class_basename($rootValue));
        };
    }

    /**
     * @param NodeValue $value
     *
     * @return NodeValue
     */
    protected function applyMiddleware(NodeValue $value): NodeValue
    {
        return $this->pipeline
            ->send($value)
            ->through($this->directiveRegistry->nodeMiddleware($value->getNode()))
            ->via('handleNode')
            ->then(function (NodeValue $value) {
                return $value;
            });
    }
    
    /**
     * Returns a closure that lazy loads the fields for a constructed type.
     *
     * @param NodeValue $nodeValue
     *
     * @return \Closure
     */
    protected function resolveFieldsFunction(NodeValue $nodeValue): \Closure
    {
        return function() use ($nodeValue){
            return collect($nodeValue->getNodeFields())
                ->mapWithKeys(function ($field) use ($nodeValue) {
                    $fieldValue = $this->valueFactory->field($nodeValue, $field);
                    
                    return [
                        $fieldValue->getFieldName() => $this->fieldFactory->handle($fieldValue),
                    ];
                })->toArray();
        };
    }
}
