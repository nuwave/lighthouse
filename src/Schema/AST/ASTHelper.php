<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\AST;

use GraphQL\Error\SyntaxError;
use GraphQL\Executor\Values;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\EnumTypeExtensionNode;
use GraphQL\Language\AST\EnumValueDefinitionNode;
use GraphQL\Language\AST\EnumValueNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeExtensionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeExtensionNode;
use GraphQL\Language\AST\ListTypeNode;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\NonNullTypeNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeExtensionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeExtensionNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeExtensionNode;
use GraphQL\Language\AST\ValueNode;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\Directive as DirectiveDefinition;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\EnumValueDefinition;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\AST;
use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\DirectiveLocator;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Directives\ModelDirective;
use Nuwave\Lighthouse\Schema\Directives\NamespaceDirective;
use Nuwave\Lighthouse\Support\Contracts\Directive as DirectiveInterface;

class ASTHelper
{
    /**
     * Merge two lists of AST nodes.
     *
     * @template TNode of \GraphQL\Language\AST\Node
     *
     * @param  \GraphQL\Language\AST\NodeList<TNode>|array<TNode>  $original
     * @param  \GraphQL\Language\AST\NodeList<TNode>|array<TNode>  $addition
     * @param  bool  $overwriteDuplicates  By default, this function throws if a collision occurs.
     *                                     If set to true, the fields of the original list will be overwritten.
     *
     * @return \GraphQL\Language\AST\NodeList<TNode>
     */
    public static function mergeUniqueNodeList(NodeList|array $original, NodeList|array $addition, bool $overwriteDuplicates = false): NodeList
    {
        $newNames = (new Collection($addition))
            ->pluck('name.value')
            ->filter()
            ->all();

        $remainingDefinitions = (new Collection($original))
            ->reject(static function (Node $definition) use ($newNames, $overwriteDuplicates): bool {
                assert(property_exists($definition, 'name'));
                $oldName = $definition->name->value;
                $collisionOccurred = in_array($oldName, $newNames);
                if ($collisionOccurred && ! $overwriteDuplicates) {
                    throw new DefinitionException(
                        static::duplicateDefinition($oldName),
                    );
                }

                return $collisionOccurred;
            })
            ->values()
            ->all();

        /**
         * Since we did not modify the passed in lists, the types did not change.
         *
         * @var \GraphQL\Language\AST\NodeList<TNode> $merged
         */
        $merged = (new NodeList($remainingDefinitions))->merge($addition);

        return $merged;
    }

    /**
     * @template TNode of \GraphQL\Language\AST\Node
     *
     * @param  \GraphQL\Language\AST\NodeList<TNode>  $nodeList
     * @param  TNode  $node
     *
     * @return \GraphQL\Language\AST\NodeList<TNode>
     */
    public static function prepend(NodeList $nodeList, Node $node): NodeList
    {
        return (new NodeList([$node]))->merge($nodeList);
    }

    public static function duplicateDefinition(string $oldName): string
    {
        return "Duplicate definition {$oldName} found when merging.";
    }

    /** Unwrap lists and non-nulls and get the name of the contained type. */
    public static function getUnderlyingTypeName(Node $definition): string
    {
        $namedType = static::getUnderlyingNamedTypeNode($definition);

        return $namedType->name->value;
    }

    /** Unwrap lists and non-nulls and get the named type within. */
    public static function getUnderlyingNamedTypeNode(Node $node): NamedTypeNode
    {
        if ($node instanceof NamedTypeNode) {
            return $node;
        }

        if (
            $node instanceof NonNullTypeNode
            || $node instanceof ListTypeNode
            || $node instanceof FieldDefinitionNode
            || $node instanceof InputValueDefinitionNode
        ) {
            return static::getUnderlyingNamedTypeNode($node->type);
        }

        throw new DefinitionException("The node '{$node->kind}' does not have a type associated with it.");
    }

    /**
     * Extract a named argument from a given directive node.
     *
     * @param  mixed  $default is returned if the directive does not have the argument
     */
    public static function directiveArgValue(DirectiveNode $directive, string $name, mixed $default = null): mixed
    {
        $arg = static::firstByName($directive->arguments, $name);

        return $arg !== null
            ? AST::valueFromASTUntyped($arg->value)
            : $default;
    }

    /**
     * Return the PHP internal value of an arguments default value.
     *
     * @param  \GraphQL\Language\AST\ValueNode&\GraphQL\Language\AST\Node  $defaultValue
     * @param  \GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\InputType  $argumentType
     */
    public static function defaultValueForArgument(ValueNode $defaultValue, Type $argumentType): mixed
    {
        // webonyx/graphql-php expects the internal value here, whereas the
        // SDL uses the ENUM's name, so we run the conversion here
        if ($argumentType instanceof EnumType) {
            assert($defaultValue instanceof EnumValueNode);

            $internalValue = $argumentType->getValue($defaultValue->value);
            assert($internalValue instanceof EnumValueDefinition);

            return $internalValue->value;
        }

        return AST::valueFromAST($defaultValue, $argumentType);
    }

    /** Get a directive with the given name if it is defined upon the node, assuming it is only used once. */
    public static function directiveDefinition(Node $definitionNode, string $name): ?DirectiveNode
    {
        foreach (static::directiveDefinitions($definitionNode, $name) as $directive) {
            return $directive;
        }

        return null;
    }

    /**
     * Get all directives with the given name if it is defined upon the node.
     *
     * @return iterable<\GraphQL\Language\AST\DirectiveNode>
     */
    public static function directiveDefinitions(Node $definitionNode, string $name): iterable
    {
        if (! property_exists($definitionNode, 'directives')) {
            $nodeClassWithoutDirectives = $definitionNode::class;
            throw new \Exception("Expected Node class with property `directives`, got: {$nodeClassWithoutDirectives}.");
        }

        /** @var \GraphQL\Language\AST\NodeList<\GraphQL\Language\AST\DirectiveNode> $directives */
        $directives = $definitionNode->directives;

        return static::filterByName($directives, $name);
    }

    /** Check if a node has a directive with the given name on it. */
    public static function hasDirective(Node $definitionNode, string $name): bool
    {
        return static::directiveDefinition($definitionNode, $name) !== null;
    }

    /**
     * Out of a list of nodes, get the ones that matches the given name.
     *
     * @template TNode of \GraphQL\Language\AST\Node
     *
     * @param  iterable<TNode>  $nodes
     *
     * @return iterable<TNode>
     */
    public static function filterByName(iterable $nodes, string $name): iterable
    {
        foreach ($nodes as $node) {
            if (! property_exists($node, 'name')) {
                throw new \Exception('Expected a Node with a name property, got: ' . $node::class);
            }

            if ($node->name->value === $name) {
                yield $node;
            }
        }
    }

    /**
     * Out of a list of nodes, get the first that matches the given name.
     *
     * @template TNode of \GraphQL\Language\AST\Node
     *
     * @param  iterable<TNode>  $nodes
     *
     * @return TNode|null
     */
    public static function firstByName(iterable $nodes, string $name): ?Node
    {
        foreach (static::filterByName($nodes, $name) as $node) {
            return $node;
        }

        return null;
    }

    /**
     * Does the given list of nodes contain a node with the given name?
     *
     * @param  iterable<\GraphQL\Language\AST\Node>  $nodes
     */
    public static function hasNode(iterable $nodes, string $name): bool
    {
        return static::firstByName($nodes, $name) !== null;
    }

    /** Directives might have an additional namespace associated with them, @see \Nuwave\Lighthouse\Schema\Directives\NamespaceDirective. */
    public static function namespaceForDirective(Node $definitionNode, string $directiveName): ?string
    {
        $namespaceDirective = static::directiveDefinition($definitionNode, NamespaceDirective::NAME);

        return $namespaceDirective !== null
            ? static::directiveArgValue($namespaceDirective, $directiveName)
            : null;
    }

    /** Attach directive to all registered object type fields. */
    public static function attachDirectiveToObjectTypeFields(DocumentAST $documentAST, DirectiveNode $directive): void
    {
        foreach ($documentAST->types as $typeDefinition) {
            if ($typeDefinition instanceof ObjectTypeDefinitionNode) {
                foreach ($typeDefinition->fields as $fieldDefinition) {
                    $fieldDefinition->directives = static::prepend($fieldDefinition->directives, $directive);
                }
            }
        }
    }

    /** Checks the given type to see whether it implements the given interface. */
    public static function typeImplementsInterface(ObjectTypeDefinitionNode $type, string $interfaceName): bool
    {
        return static::hasNode($type->interfaces, $interfaceName);
    }

    public static function addDirectiveToFields(DirectiveNode $directiveNode, ObjectTypeDefinitionNode|ObjectTypeExtensionNode|InterfaceTypeDefinitionNode|InterfaceTypeExtensionNode &$typeWithFields): void
    {
        $name = $directiveNode->name->value;

        $directiveLocator = Container::getInstance()->make(DirectiveLocator::class);
        $directive = $directiveLocator->resolve($name);
        $directiveDefinition = static::extractDirectiveDefinition($directive::definition());

        foreach ($typeWithFields->fields as $fieldDefinition) {
            // If the field already has the same directive defined, and it is not
            // a repeatable directive, skip over it.
            // Field directives are more specific than those defined on a type.
            if (
                static::hasDirective($fieldDefinition, $name)
                && ! $directiveDefinition->repeatable
            ) {
                continue;
            }

            $fieldDefinition->directives = static::prepend($fieldDefinition->directives, $directiveNode);
        }
    }

    /**
     * Create a fully qualified base for a generated name that belongs to an argument.
     *
     * We have to make sure it is unique in the schema. Even though
     * this name becomes a bit verbose, it is also very unlikely to collide
     * with a random user defined type.
     *
     * @example ParentNameFieldNameArgName
     */
    public static function qualifiedArgType(
        InputValueDefinitionNode &$argDefinition,
        FieldDefinitionNode &$parentField,
        ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType,
    ): string {
        return Str::studly($parentType->name->value)
            . Str::studly($parentField->name->value)
            . Str::studly($argDefinition->name->value);
    }

    /** Given a collection of directives, returns the string value for the deprecation reason. */
    public static function deprecationReason(EnumValueDefinitionNode|FieldDefinitionNode $node): ?string
    {
        $deprecated = Values::getDirectiveValues(
            DirectiveDefinition::deprecatedDirective(),
            $node,
        );

        return $deprecated['reason'] ?? null;
    }

    public static function extractDirectiveDefinition(string $definitionString): DirectiveDefinitionNode
    {
        try {
            $document = Parser::parse($definitionString);
        } catch (SyntaxError $syntaxError) {
            throw new DefinitionException(
                "Encountered syntax error while parsing this directive definition:\n\n{$definitionString}",
                $syntaxError->getCode(),
                $syntaxError,
            );
        }

        /** @var \GraphQL\Language\AST\DirectiveDefinitionNode|null $directive */
        $directive = null;
        foreach ($document->definitions as $definitionNode) {
            if ($definitionNode instanceof DirectiveDefinitionNode) {
                if ($directive !== null) {
                    throw new DefinitionException("Found multiple directives while trying to extract a single directive from this definition:\n\n{$definitionString}");
                }

                $directive = $definitionNode;
            }
        }

        if ($directive === null) {
            throw new DefinitionException("Found no directive while trying to extract a single directive from this definition:\n\n{$definitionString}");
        }

        return $directive;
    }

    /** @return (\GraphQL\Language\AST\Node&\GraphQL\Language\AST\TypeDefinitionNode)|null */
    public static function underlyingType(FieldDefinitionNode $field): ?Node
    {
        $typeName = static::getUnderlyingTypeName($field);

        $standardTypes = Type::getStandardTypes();
        if (isset($standardTypes[$typeName])) {
            return Parser::scalarTypeDefinition("scalar {$typeName}");
        }

        $astBuilder = Container::getInstance()->make(ASTBuilder::class);
        $documentAST = $astBuilder->documentAST();

        return $documentAST->types[$typeName] ?? null;
    }

    /** Take a guess at the name of the model associated with the given node. */
    public static function modelName(Node $definitionNode): ?string
    {
        if ($definitionNode instanceof FieldDefinitionNode) {
            $modelDefinitionNode = static::underlyingType($definitionNode);
            if ($modelDefinitionNode === null) {
                return static::getUnderlyingTypeName($definitionNode);
            }
        } else {
            $modelDefinitionNode = $definitionNode;
        }

        if ($modelDefinitionNode instanceof ObjectTypeDefinitionNode
            || $modelDefinitionNode instanceof InterfaceTypeDefinitionNode
            || $modelDefinitionNode instanceof UnionTypeDefinitionNode
        ) {
            return ModelDirective::modelClass($modelDefinitionNode)
                ?? $modelDefinitionNode->name->value;
        }

        return null;
    }

    public static function internalFieldName(FieldDefinitionNode $field): string
    {
        $renameDirectiveNode = static::directiveDefinition($field, 'rename');

        return $renameDirectiveNode
            ? static::directiveArgValue($renameDirectiveNode, 'attribute')
            : $field->name->value;
    }

    /** Adds a directive to a node, instantiates and maybe hydrates it and returns the instance. */
    public static function addDirectiveToNode(string $directiveSource, ScalarTypeDefinitionNode|ScalarTypeExtensionNode|ObjectTypeDefinitionNode|ObjectTypeExtensionNode|InterfaceTypeDefinitionNode|InterfaceTypeExtensionNode|UnionTypeDefinitionNode|UnionTypeExtensionNode|EnumTypeDefinitionNode|EnumTypeExtensionNode|InputObjectTypeDefinitionNode|InputObjectTypeExtensionNode|FieldDefinitionNode|InputValueDefinitionNode|EnumValueDefinitionNode $node): DirectiveInterface
    {
        $directiveNode = Parser::directive($directiveSource);
        $node->directives[] = $directiveNode;

        $directiveLocator = Container::getInstance()->make(DirectiveLocator::class);
        $directiveInstance = $directiveLocator->create($directiveNode->name->value);
        if ($directiveInstance instanceof BaseDirective) {
            $directiveInstance->hydrate($directiveNode, $node);
        }

        return $directiveInstance;
    }
}
