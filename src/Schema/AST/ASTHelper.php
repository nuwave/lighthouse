<?php

namespace Nuwave\Lighthouse\Schema\AST;

use GraphQL\Utils\AST;
use GraphQL\Language\Parser;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;
use Illuminate\Support\Collection;
use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\ListTypeNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\NonNullTypeNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
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
     * This function will merge two lists uniquely by name.
     *
     * @param  \GraphQL\Language\AST\NodeList|array  $original
     * @param  \GraphQL\Language\AST\NodeList|array  $addition
     * @param  bool  $overwriteDuplicates By default this throws if a collision occurs. If
     *                                            this is set to true, the fields of the original list will be overwritten.
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
                $collisionOccurred = in_array(
                    $oldName,
                    $newNames
                );

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
     * Create a clone of the original node.
     *
     * @param  \GraphQL\Language\AST\Node  $node
     * @return \GraphQL\Language\AST\Node
     */
    public static function cloneNode(Node $node): Node
    {
        return AST::fromArray(
            $node->toArray(true)
        );
    }

    /**
     * @param  \GraphQL\Language\AST\Node  $definition
     * @return string
     */
    public static function getUnderlyingTypeName(Node $definition): string
    {
        $type = $definition->type;
        if ($type instanceof ListTypeNode || $type instanceof NonNullTypeNode) {
            $type = self::getUnderlyingNamedTypeNode($type);
        }

        return $type->name->value;
    }

    /**
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
        return (new Collection($directiveDefinition->arguments))
            ->contains(function (ArgumentNode $argumentNode) use ($name): bool {
                return $argumentNode->name->value === $name;
            });
    }

    /**
     * @param  \GraphQL\Language\AST\DirectiveNode  $directive
     * @param  string  $name
     * @param  mixed|null  $default
     * @return mixed|null
     */
    public static function directiveArgValue(DirectiveNode $directive, string $name, $default = null)
    {
        $arg = (new Collection($directive->arguments))
            ->first(function (ArgumentNode $argumentNode) use ($name): bool {
                return $argumentNode->name->value === $name;
            });

        return $arg
            ? self::argValue($arg, $default)
            : $default;
    }

    /**
     * Get argument's value.
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
     * This can be at most one directive, since directives can only be used once per location.
     *
     * @param  \GraphQL\Language\AST\Node  $definitionNode
     * @param  string  $name
     * @return \GraphQL\Language\AST\DirectiveNode|null
     */
    public static function directiveDefinition(Node $definitionNode, string $name): ?DirectiveNode
    {
        return (new Collection($definitionNode->directives))
            ->first(function (DirectiveNode $directiveDefinitionNode) use ($name): bool {
                return $directiveDefinitionNode->name->value === $name;
            });
    }

    /**
     * Check if a node has a particular directive defined upon it.
     *
     * @param  \GraphQL\Language\AST\Node  $definitionNode
     * @param  string  $name
     * @return bool
     */
    public static function hasDirectiveDefinition(Node $definitionNode, string $name): bool
    {
        return (new Collection($definitionNode->directives))
            ->contains(function (DirectiveNode $directiveDefinitionNode) use ($name): bool {
                return $directiveDefinitionNode->name->value === $name;
            });
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
     * Attach directive to all registered object type fields.
     *
     * @param  \Nuwave\Lighthouse\Schema\AST\DocumentAST  $documentAST
     * @param  \GraphQL\Language\AST\DirectiveNode  $directive
     * @return \Nuwave\Lighthouse\Schema\AST\DocumentAST
     */
    public static function attachDirectiveToObjectTypeFields(DocumentAST $documentAST, DirectiveNode $directive): DocumentAST
    {
        return $documentAST->objectTypeDefinitions()
            ->reduce(
                function (DocumentAST $document, ObjectTypeDefinitionNode $objectType) use ($directive): DocumentAST {
                    if (! data_get($objectType, 'name.value')) {
                        return $document;
                    }

                    $objectType->fields = new NodeList(
                        (new Collection($objectType->fields))
                            ->map(function (FieldDefinitionNode $field) use ($directive): FieldDefinitionNode {
                                $field->directives = $field->directives->merge([$directive]);

                                return $field;
                            })
                            ->all()
                    );

                    $document->setDefinition($objectType);

                    return $document;
                },
                $documentAST
            );
    }

    /**
     * This adds an Interface called "Node" to an ObjectType definition.
     *
     * @param  \GraphQL\Language\AST\ObjectTypeDefinitionNode  $objectType
     * @param  \Nuwave\Lighthouse\Schema\AST\DocumentAST  $documentAST
     * @return \Nuwave\Lighthouse\Schema\AST\DocumentAST
     */
    public static function attachNodeInterfaceToObjectType(ObjectTypeDefinitionNode $objectType, DocumentAST $documentAST): DocumentAST
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

        return $documentAST->setDefinition($objectType);
    }
}
