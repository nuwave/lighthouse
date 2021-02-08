<?php

namespace Nuwave\Lighthouse\Schema\AST;

use Exception;
use GraphQL\Error\SyntaxError;
use GraphQL\Executor\Values;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeExtensionNode;
use GraphQL\Language\AST\ValueNode;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\AST;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\DirectiveLocator;
use Nuwave\Lighthouse\Schema\Directives\NamespaceDirective;

class ASTHelper
{
    /**
     * Merge two lists of AST nodes.
     *
     * @template TNode of \GraphQL\Language\AST\Node
     * @param  \GraphQL\Language\AST\NodeList<TNode>|array<TNode>  $original
     * @param  \GraphQL\Language\AST\NodeList<TNode>|array<TNode>  $addition
     * @param  bool  $overwriteDuplicates  By default this function throws if a collision occurs.
     *                                     If set to true, the fields of the original list will be overwritten.
     * @return \GraphQL\Language\AST\NodeList<TNode>
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
                        static::duplicateDefinition($oldName)
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

    public static function duplicateDefinition(string $oldName): string
    {
        return "Duplicate definition {$oldName} found when merging.";
    }

    /**
     * Unwrap lists and non-nulls and get the name of the contained type.
     */
    public static function getUnderlyingTypeName(Node $definition): string
    {
        $namedType = self::getUnderlyingNamedTypeNode($definition);

        return $namedType->name->value;
    }

    /**
     * Unwrap lists and non-nulls and get the named type within.
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
     * Does the given field have an argument of the given name?
     */
    public static function fieldHasArgument(FieldDefinitionNode $fieldDefinition, string $name): bool
    {
        return self::firstByName($fieldDefinition->arguments, $name) !== null;
    }

    /**
     * Does the given directive have an argument of the given name?
     */
    public static function directiveHasArgument(DirectiveNode $directiveDefinition, string $name): bool
    {
        return self::firstByName($directiveDefinition->arguments, $name) !== null;
    }

    /**
     * Extract a named argument from a given directive node.
     *
     * @param  mixed  $default Is returned if the directive does not have the argument.
     * @return mixed The value given to the directive.
     */
    public static function directiveArgValue(DirectiveNode $directive, string $name, $default = null)
    {
        /** @var \GraphQL\Language\AST\ArgumentNode|null $arg */
        $arg = self::firstByName($directive->arguments, $name);

        return $arg !== null
            ? AST::valueFromASTUntyped($arg->value)
            : $default;
    }

    /**
     * Return the PHP internal value of an arguments default value.
     *
     * @param  \GraphQL\Language\AST\ValueNode&\GraphQL\Language\AST\Node  $defaultValue
     * @param  \GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\InputType  $argumentType
     * @return mixed The plain PHP value.
     */
    public static function defaultValueForArgument(ValueNode $defaultValue, Type $argumentType)
    {
        // webonyx/graphql-php expects the internal value here, whereas the
        // SDL uses the ENUM's name, so we run the conversion here
        if ($argumentType instanceof EnumType) {
            /** @var \GraphQL\Language\AST\EnumValueNode $defaultValue */

            /** @var \GraphQL\Type\Definition\EnumValueDefinition $internalValue */
            $internalValue = $argumentType->getValue($defaultValue->value);

            return $internalValue->value;
        }

        // @phpstan-ignore-next-line any ValueNode is fine
        return AST::valueFromAST($defaultValue, $argumentType);
    }

    /**
     * Get a directive with the given name if it is defined upon the node.
     *
     * As of now, directives may only be used once per location.
     */
    public static function directiveDefinition(Node $definitionNode, string $name): ?DirectiveNode
    {
        if (! property_exists($definitionNode, 'directives')) {
            throw new Exception('Expected Node class with property `directives`, got: '.get_class($definitionNode));
        }
        /** @var \GraphQL\Language\AST\NodeList<\GraphQL\Language\AST\DirectiveNode> $directives */
        $directives = $definitionNode->directives;

        return self::firstByName($directives, $name);
    }

    /**
     * Check if a node has a directive with the given name on it.
     */
    public static function hasDirective(Node $definitionNode, string $name): bool
    {
        return self::directiveDefinition($definitionNode, $name) !== null;
    }

    /**
     * Out of a list of nodes, get the first that matches the given name.
     *
     * @template TNode of \GraphQL\Language\AST\Node
     * @param  iterable<TNode> $nodes
     * @return TNode|null
     */
    public static function firstByName($nodes, string $name): ?Node
    {
        foreach ($nodes as $node) {
            if (! property_exists($node, 'name')) {
                throw new Exception('Expected a Node with a name property, got: '.get_class($node));
            }

            if ($node->name->value === $name) {
                return $node;
            }
        }

        return null;
    }

    /**
     * Directives might have an additional namespace associated with them, set via the "@namespace" directive.
     */
    public static function getNamespaceForDirective(Node $definitionNode, string $directiveName): string
    {
        $namespaceDirective = static::directiveDefinition($definitionNode, NamespaceDirective::NAME);

        return $namespaceDirective !== null
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
     */
    public static function attachDirectiveToObjectTypeFields(DocumentAST $documentAST, DirectiveNode $directive): void
    {
        foreach ($documentAST->types as $typeDefinition) {
            if ($typeDefinition instanceof ObjectTypeDefinitionNode) {
                /** @var iterable<\GraphQL\Language\AST\FieldDefinitionNode> $fieldDefinitions */
                $fieldDefinitions = $typeDefinition->fields;
                foreach ($fieldDefinitions as $fieldDefinition) {
                    $fieldDefinition->directives [] = $directive;
                }
            }
        }
    }

    /**
     * Checks the given type to see whether it implements the given interface.
     */
    public static function typeImplementsInterface(ObjectTypeDefinitionNode $type, string $interfaceName): bool
    {
        return self::firstByName($type->interfaces, $interfaceName) !== null;
    }

    /**
     * @param  \GraphQL\Language\AST\ObjectTypeDefinitionNode|\GraphQL\Language\AST\ObjectTypeExtensionNode|mixed  $objectType
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

        /** @var \Nuwave\Lighthouse\Schema\DirectiveLocator $directiveLocator */
        $directiveLocator = app(DirectiveLocator::class);
        $directive = $directiveLocator->resolve($name);
        $directiveDefinition = self::extractDirectiveDefinition($directive::definition());

        /** @var iterable<\GraphQL\Language\AST\FieldDefinitionNode> $fieldDefinitions */
        $fieldDefinitions = $objectType->fields;
        foreach ($fieldDefinitions as $fieldDefinition) {
            // If the field already has the same directive defined, and it is not
            // a repeatable directive, skip over it.
            // Field directives are more specific than those defined on a type.
            if (
                self::hasDirective($fieldDefinition, $name)
                && ! $directiveDefinition->repeatable
            ) {
                continue;
            }

            $fieldDefinition->directives [] = $directiveNode;
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
        ObjectTypeDefinitionNode &$parentType
    ): string {
        return Str::studly($parentType->name->value)
            .Str::studly($parentField->name->value)
            .Str::studly($argDefinition->name->value);
    }

    /**
     * Given a collection of directives, returns the string value for the deprecation reason.
     *
     * @param  \GraphQL\Language\AST\EnumValueDefinitionNode|\GraphQL\Language\AST\FieldDefinitionNode  $node
     * @return string
     */
    public static function deprecationReason(Node $node): ?string
    {
        $deprecated = Values::getDirectiveValues(
            Directive::deprecatedDirective(),
            $node
        );

        return $deprecated['reason'] ?? null;
    }

    /**
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    public static function extractDirectiveDefinition(string $definitionString): DirectiveDefinitionNode
    {
        try {
            $document = Parser::parse($definitionString);
        } catch (SyntaxError $error) {
            throw new DefinitionException(
                "Encountered syntax error while parsing this directive definition::\n\n{$definitionString}",
                $error->getCode(),
                $error
            );
        }

        /** @var \GraphQL\Language\AST\DirectiveDefinitionNode|null $directive */
        $directive = null;
        foreach ($document->definitions as $definitionNode) {
            if ($definitionNode instanceof DirectiveDefinitionNode) {
                if ($directive !== null) {
                    throw new DefinitionException(
                        "Found multiple directives while trying to extract a single directive from this definition:\n\n{$definitionString}"
                    );
                }

                $directive = $definitionNode;
            }
        }

        if ($directive === null) {
            throw new DefinitionException(
                "Found no directive while trying to extract a single directive from this definition:\n\n{$definitionString}"
            );
        }

        return $directive;
    }
}
