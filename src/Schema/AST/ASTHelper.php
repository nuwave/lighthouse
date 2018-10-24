<?php

namespace Nuwave\Lighthouse\Schema\AST;

use GraphQL\Utils\AST;
use GraphQL\Language\Parser;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ValueNode;
use GraphQL\Language\AST\ListTypeNode;
use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\NonNullTypeNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\Directives\Fields\NamespaceDirective;

class ASTHelper
{
    /**
     * This function exists as a workaround for an issue within webonyx/graphql-php.
     *
     * The problem is that lists of definitions are usually NodeList objects - except
     * when the list is empty, then it is []. This function corrects that inconsistency
     * and allows the rest of our code to not worry about it until it is fixed.
     *
     * This issue is brought up here https://github.com/webonyx/graphql-php/issues/285
     * Remove this method (and possibly the entire class) once it is resolved.
     *
     * @param NodeList|array $original
     * @param NodeList|array $addition
     *
     * @return NodeList
     */
    public static function mergeNodeList($original, $addition): NodeList
    {
        if (! $original instanceof NodeList) {
            $original = new NodeList($original);
        }

        return $original->merge($addition);
    }

    /**
     * This function will merge two lists uniquely by name.
     *
     * @param NodeList|array $original
     * @param NodeList|array $addition
     * @param bool $overwriteDuplicates By default this throws if a collision occurs. If
     * this is set to true, the fields of the original list will be overwritten.
     *
     * @throws DefinitionException
     *
     * @return NodeList
     */
    public static function mergeUniqueNodeList($original, $addition, bool $overwriteDuplicates = false): NodeList
    {
        $newNames = collect($addition)
            ->pluck('name.value')
            ->filter()
            ->all();
        
        $remainingDefinitions = collect($original)
            ->reject(function ($definition) use ($newNames, $overwriteDuplicates) {
                $oldName = $definition->name->value;
                $collisionOccured = in_array(
                    $oldName,
                    $newNames
                );

                if($collisionOccured && ! $overwriteDuplicates){
                    throw new DefinitionException("Duplicate definition {$oldName} found when merging.");
                }

                return $collisionOccured;
            })
            ->values()
            ->all();

        return self::mergeNodeList($remainingDefinitions, $addition);
    }

    /**
     * Create a clone of the original node.
     *
     * @param Node $node
     *
     * @return Node
     */
    public static function cloneNode(Node $node): Node
    {
        return AST::fromArray(
            $node->toArray(true)
        );
    }

    /**
     * @param FieldDefinitionNode $field
     *
     * @throws DefinitionException
     *
     * @return string
     */
    public static function getFieldTypeName(FieldDefinitionNode $field): string
    {
        $type = $field->type;
        if ($type instanceof ListTypeNode || $type instanceof NonNullTypeNode){
            $type = self::getUnderlyingNamedTypeNode($type);
        }
        
        /** @var NamedTypeNode $type */
        return $type->name->value;
    }

    /**
     * @param Node $node
     *
     * @throws DefinitionException
     *
     * @return NamedTypeNode
     */
    public static function getUnderlyingNamedTypeNode(Node $node): NamedTypeNode
    {
        if($node instanceof NamedTypeNode){
            return $node;
        }
        
        $type = data_get($node, 'type');

        if(!$type){
            throw new DefinitionException("The node '$node->kind' does not have a type associated with it.");
        }
        
        return self::getUnderlyingNamedTypeNode($type);
    }

    /**
     * Does the given directive have an argument of the given name?
     *
     * @param DirectiveNode $directiveDefinition
     * @param string $name
     *
     * @return bool
     */
    public static function directiveHasArgument(DirectiveNode $directiveDefinition, string $name): bool
    {
        return collect($directiveDefinition->arguments)
            ->contains(function(ArgumentNode $argumentNode) use ($name){
                return $argumentNode->name->value === $name;
            });
    }

    /**
     * @param DirectiveNode $directive
     * @param string $name
     * @param mixed|null $default
     *
     * @return mixed|null
     */
    public static function directiveArgValue(DirectiveNode $directive, string $name, $default = null)
    {
        $arg = collect($directive->arguments)
            ->first(function (ArgumentNode $argumentNode) use ($name) {
                return $argumentNode->name->value === $name;
            });

        return $arg
            ? self::argValue($arg, $default)
            : $default;
    }

    /**
     * Get argument's value.
     *
     * @param ArgumentNode $arg
     * @param mixed $default
     *
     * @return mixed
     */
    public static function argValue(ArgumentNode $arg, $default = null)
    {
        $valueNode = $arg->value;

        if (! $valueNode) {
            return $default;
        }

        return AST::valueFromASTUntyped($valueNode);
    }

    /**
     * This can be at most one directive, since directives can only be used once per location.
     *
     * @param Node $definitionNode
     * @param string $name
     *
     * @return DirectiveNode|null
     */
    public static function directiveDefinition(Node $definitionNode, string $name)
    {
        return collect($definitionNode->directives)
            ->first(function (DirectiveNode $directiveDefinitionNode) use ($name) {
                return $directiveDefinitionNode->name->value === $name;
            });
    }
    
    /**
     * Directives might have an additional namespace associated with them, set via the "@namespace" directive.
     *
     * @param Node $definitionNode
     * @param string $directiveName
     *
     * @return string
     */
    public static function getNamespaceForDirective(Node $definitionNode, string $directiveName): string
    {
        $namespaceDirective = static::directiveDefinition(
            $definitionNode,
            (new NamespaceDirective)->name()
        );
    
        return $namespaceDirective
            // The namespace directive can contain an argument with the name of the
            // current directive, in which case it applies here
            ? static::directiveArgValue($namespaceDirective, $directiveName, '')
            // Default to an empty namespace if the namespace directive does not exist
            : '';
    }
    
    /**
     * This adds an Interface called "Node" to an ObjectType definition.
     *
     * @param ObjectTypeDefinitionNode $objectType
     * @param DocumentAST $documentAST
     *
     * @throws \Exception
     *
     * @return DocumentAST
     */
    public static function attachNodeInterfaceToObjectType(ObjectTypeDefinitionNode $objectType, DocumentAST $documentAST): DocumentAST
    {
        $objectType->interfaces = self::mergeNodeList(
            $objectType->interfaces,
            [
                Parser::parseType(
                    'Node',
                    ['noLocation' => true]
                )
            ]
        );
    
        $globalIdFieldDefinition = PartialParser::fieldDefinition(
            config('lighthouse.global_id_field') .': ID! @globalId'
        );
        $objectType->fields = $objectType->fields->merge([$globalIdFieldDefinition]);
        
        return $documentAST->setDefinition($objectType);
    }
}
