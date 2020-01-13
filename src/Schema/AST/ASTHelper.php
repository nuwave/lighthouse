<?php

namespace Nuwave\Lighthouse\Schema\AST;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeExtensionNode;
use GraphQL\Language\AST\ValueNode;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\AST;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\Directives\NamespaceDirective;

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
     * @param  \GraphQL\Language\AST\NodeList|array  $original
     * @param  \GraphQL\Language\AST\NodeList|array  $addition
     * @return \GraphQL\Language\AST\NodeList
     */
    public static function mergeNodeList($original, $addition): NodeList
    {
        if (! $original instanceof NodeList) {
            $original = new NodeList($original);
        }

        return $original->merge($addition);
    }

    /**
     * Merge two lists of AST nodes.
     *
     * @param  \GraphQL\Language\AST\NodeList|array  $original
     * @param  \GraphQL\Language\AST\NodeList|array  $addition
     * @param  bool  $overwriteDuplicates  By default this function throws if a collision occurs.
     *                                     If set to true, the fields of the original list will be overwritten.
     * @return \GraphQL\Language\AST\NodeList
     */
    public static function mergeUniqueNodeList($original, $addition, bool $overwriteDuplicates = false): NodeList
    {
        $newNames = (new Collection($addition))
            ->pluck('name.value')
            ->filter()
            ->all();

        $remainingDefinitions = (new Collection($original))
            ->reject(function ($definition) use ($newNames, $overwriteDuplicates): bool {
                $oldName = $definition->name->value;
                $collisionOccurred = in_array($oldName, $newNames);

                if ($collisionOccurred && ! $overwriteDuplicates) {
                    throw new DefinitionException(
                        "Duplicate definition {$oldName} found when merging."
                    );
                }

                return $collisionOccurred;
            })
            ->values()
            ->all();

        return self::mergeNodeList($remainingDefinitions, $addition);
    }

    /**
     * Unwrap lists and non-nulls and get the name of the contained type.
     *
     * @param  \GraphQL\Language\AST\Node  $definition
     * @return string
     */
    public static function getUnderlyingTypeName(Node $definition): string
    {
        $namedType = self::getUnderlyingNamedTypeNode($definition);

        return $namedType->name->value;
    }

    /**
     * Unwrap lists and non-nulls and get the named type within.
     *
     * @param  \GraphQL\Language\AST\Node  $node
     * @return \GraphQL\Language\AST\NamedTypeNode
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    public static function getUnderlyingNamedTypeNode(Node $node): NamedTypeNode
    {
        if ($node instanceof NamedTypeNode) {
            return $node;
        }

        $type = data_get($node, 'type');

        if (! $type) {
            throw new DefinitionException(
                "The node '$node->kind' does not have a type associated with it."
            );
        }

        return self::getUnderlyingNamedTypeNode($type);
    }

    /**
     * Does the given directive have an argument of the given name?
     *
     * @param  \GraphQL\Language\AST\DirectiveNode  $directiveDefinition
     * @param  string  $name
     * @return bool
     */
    public static function directiveHasArgument(DirectiveNode $directiveDefinition, string $name): bool
    {
        return self::firstByName($directiveDefinition->arguments, $name) !== null;
    }

    /**
     * Extract a named argument from a given directive node.
     *
     * @param  \GraphQL\Language\AST\DirectiveNode  $directive
     * @param  string  $name
     * @param  mixed  $default
     * @return mixed
     */
    public static function directiveArgValue(DirectiveNode $directive, string $name, $default = null)
    {
        $arg = self::firstByName($directive->arguments, $name);

        return $arg
            ? self::argValue($arg, $default)
            : $default;
    }

    /**
     * Get the value of an argument node.
     *
     * @param  \GraphQL\Language\AST\ArgumentNode  $arg
     * @param  mixed  $default
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
     * Return the PHP internal value of an arguments default value.
     *
     * @param  \GraphQL\Language\AST\ValueNode  $defaultValue
     * @param  \GraphQL\Type\Definition\Type  $argumentType
     * @return mixed
     */
    public static function defaultValueForArgument(ValueNode $defaultValue, Type $argumentType)
    {
        // webonyx/graphql-php expects the internal value here, whereas the
        // SDL uses the ENUM's name, so we run the conversion here
        if ($argumentType instanceof EnumType) {
            return $argumentType
                ->getValue($defaultValue->value)
                ->value;
        }

        return AST::valueFromAST($defaultValue, $argumentType);
    }

    /**
     * Get a directive with the given name if it is defined upon the node.
     *
     * As of now, directives may only be used once per location.
     *
     * @param  \GraphQL\Language\AST\Node  $definitionNode
     * @param  string  $name
     * @return \GraphQL\Language\AST\DirectiveNode|null
     */
    public static function directiveDefinition(Node $definitionNode, string $name): ?DirectiveNode
    {
        return self::firstByName($definitionNode->directives, $name);
    }

    /**
     * Check if a node has a directive with the given name on it.
     *
     * @param  \GraphQL\Language\AST\Node  $definitionNode
     * @param  string  $name
     * @return \GraphQL\Language\AST\DirectiveNode|null
     */
    public static function hasDirective(Node $definitionNode, string $name): bool
    {
        return self::directiveDefinition($definitionNode, $name) !== null;
    }

    /**
     * Out of a list of nodes, get the first that matches the given name.
     *
     * @param  \GraphQL\Language\AST\NodeList|\GraphQL\Language\AST\Node[] $nodes
     * @param  string  $name
     * @return \GraphQL\Language\AST\Node|null
     */
    public static function firstByName($nodes, string $name): ?Node
    {
        /** @var \GraphQL\Language\AST\Node $node */
        foreach ($nodes as $node) {
            if ($node->name->value === $name) {
                return $node;
            }
        }

        return null;
    }

    /**
     * Directives might have an additional namespace associated with them, set via the "@namespace" directive.
     *
     * @param  \GraphQL\Language\AST\Node  $definitionNode
     * @param  string  $directiveName
     * @return string
     */
    public static function getNamespaceForDirective(Node $definitionNode, string $directiveName): string
    {
        $namespaceDirective = static::directiveDefinition($definitionNode, NamespaceDirective::NAME);

        return $namespaceDirective
            // The namespace directive can contain an argument with the name of the
            // current directive, in which case it applies here
            ? static::directiveArgValue($namespaceDirective, $directiveName, '')
            // Default to an empty namespace if the namespace directive does not exist
            : '';
    }

    /**
     * Attach directive to all registered object type fields.
     *
     * @param  \Nuwave\Lighthouse\Schema\AST\DocumentAST  $documentAST
     * @param  \GraphQL\Language\AST\DirectiveNode  $directive
     * @return void
     */
    public static function attachDirectiveToObjectTypeFields(DocumentAST $documentAST, DirectiveNode $directive): void
    {
        foreach ($documentAST->types as $typeDefinition) {
            if ($typeDefinition instanceof ObjectTypeDefinitionNode) {
                foreach ($typeDefinition->fields as $fieldDefinition) {
                    $fieldDefinition->directives = $fieldDefinition->directives->merge([$directive]);
                }
            }
        }
    }

    /**
     * Add the "Node" interface and a global ID field to an object type.
     *
     * @param  \GraphQL\Language\AST\ObjectTypeDefinitionNode  $objectType
     * @return \GraphQL\Language\AST\ObjectTypeDefinitionNode
     */
    public static function attachNodeInterfaceToObjectType(ObjectTypeDefinitionNode $objectType): ObjectTypeDefinitionNode
    {
        $objectType->interfaces = self::mergeNodeList(
            $objectType->interfaces,
            [
                Parser::parseType(
                    'Node',
                    ['noLocation' => true]
                ),
            ]
        );

        $globalIdFieldDefinition = PartialParser::fieldDefinition(
            config('lighthouse.global_id_field').': ID! @globalId'
        );
        $objectType->fields = $objectType->fields->merge([$globalIdFieldDefinition]);

        return $objectType;
    }

    /**
     * Checks the given type to see whether it implements the given interface.
     *
     * @param  \GraphQL\Language\AST\ObjectTypeDefinitionNode  $type
     * @param  string  $interfaceName
     *
     * @return bool
     */
    public static function typeImplementsInterface(ObjectTypeDefinitionNode $type, string $interfaceName): bool
    {
        return self::firstByName($type->interfaces, $interfaceName) !== null;
    }

    /**
     * @param  \GraphQL\Language\AST\ObjectTypeDefinitionNode|\GraphQL\Language\AST\ObjectTypeExtensionNode  $objectType
     * @param  \GraphQL\Language\AST\DirectiveNode  $directiveNode
     * @return void
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    public static function addDirectiveToFields(DirectiveNode $directiveNode, &$objectType): void
    {
        $name = $directiveNode->name->value;

        if (
            ! $objectType instanceof ObjectTypeDefinitionNode
            && ! $objectType instanceof ObjectTypeExtensionNode
        ) {
            throw new DefinitionException(
                "The @{$name} directive may only be placed on fields or object types."
            );
        }

        /** @var \GraphQL\Language\AST\FieldDefinitionNode $fieldDefinition */
        foreach ($objectType->fields as $fieldDefinition) {
            // If the field already has the same directive defined, skip over it.
            // Field directives are more specific than those defined on a type.
            if (self::hasDirective($fieldDefinition, $name)) {
                continue;
            }

            $fieldDefinition->directives = $fieldDefinition->directives->merge([$directiveNode]);
        }
    }
}
