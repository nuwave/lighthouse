<?php

namespace Nuwave\Lighthouse\Schema\AST;

use Exception;
use GraphQL\Error\SyntaxError;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\TypeExtensionNode;
use GraphQL\Language\Parser;
use Nuwave\Lighthouse\Exceptions\ParseException;
use Serializable;

class DocumentAST implements Serializable
{
    /**
     * The types within the schema.
     *
     * ['foo' => FooType].
     *
     * @var \GraphQL\Language\AST\NodeList<\GraphQL\Language\AST\TypeDefinitionNode&\GraphQL\Language\AST\Node>|array<string, \GraphQL\Language\AST\TypeDefinitionNode&\GraphQL\Language\AST\Node>
     */
    public $types = [];

    /**
     * The type extensions within the parsed document.
     *
     * Will NOT be kept after unserialization, as the type
     * extensions are merged with the types before.
     *
     * @var array<string, array<int, \GraphQL\Language\AST\TypeExtensionNode&\GraphQL\Language\AST\Node>>
     */
    public $typeExtensions = [];

    /**
     * Client directive definitions.
     *
     * ['foo' => FooDirective].
     *
     * @var \GraphQL\Language\AST\NodeList<\GraphQL\Language\AST\DirectiveDefinitionNode>|array<string, \GraphQL\Language\AST\DirectiveDefinitionNode>
     */
    public $directives = [];

    /**
     * Create a new DocumentAST instance from a schema.
     *
     * @return static
     *
     * @throws \Nuwave\Lighthouse\Exceptions\ParseException
     */
    public static function fromSource(string $schema): self
    {
        try {
            $documentNode = Parser::parse(
                $schema,
                // Ignore location since it only bloats the AST
                ['noLocation' => true]
            );
        } catch (SyntaxError $syntaxError) {
            // Throw our own error class instead, since otherwise a schema definition
            // error would get rendered to the Client.
            throw new ParseException(
                $syntaxError->getMessage()
            );
        }

        $instance = new static;

        foreach ($documentNode->definitions as $definition) {
            if ($definition instanceof TypeDefinitionNode) {
                // Store the types in an associative array for quick lookup
                $instance->types[$definition->name->value] = $definition;
            } elseif ($definition instanceof TypeExtensionNode) {
                // Multiple type extensions for the same name can exist
                $instance->typeExtensions[$definition->name->value] [] = $definition;
            } elseif ($definition instanceof DirectiveDefinitionNode) {
                $instance->directives[$definition->name->value] = $definition;
            } else {
                throw new Exception(
                    'Unknown definition type'
                );
            }
        }

        return $instance;
    }

    /**
     * Serialize the final AST.
     *
     * We exclude the type extensions stored in $typeExtensions,
     * as they are merged with the actual types at this point.
     */
    public function serialize(): string
    {
        $nodeToArray = function (Node $node): array {
            return $node->toArray(true);
        };

        return serialize([
            // @phpstan-ignore-next-line Before serialization, those are arrays
            'types' => array_map($nodeToArray, $this->types),
            // @phpstan-ignore-next-line Before serialization, those are arrays
            'directives' => array_map($nodeToArray, $this->directives),
        ]);
    }

    /**
     * Unserialize the AST.
     *
     * @param string $serialized
     */
    public function unserialize($serialized): void
    {
        [
            'types' => $types,
            'directives' => $directives,
        ] = unserialize($serialized);

        // Utilize the NodeList for lazy unserialization for performance gains.
        // Until they are accessed by name, they are kept in their array form.
        $this->types = new NodeList($types);
        $this->directives = new NodeList($directives);
    }

    /**
     * Set a type definition in the AST.
     *
     * This operation will overwrite existing definitions with the same name.
     *
     * @return $this
     */
    public function setTypeDefinition(TypeDefinitionNode $type): self
    {
        $this->types[$type->name->value] = $type;

        return $this;
    }

    /**
     * Set a directive definition in the AST.
     *
     * This operation will overwrite existing definitions with the same name.
     *
     * @return $this
     */
    public function setDirectiveDefinition(DirectiveDefinitionNode $directive): self
    {
        $this->directives[$directive->name->value] = $directive;

        return $this;
    }
}
