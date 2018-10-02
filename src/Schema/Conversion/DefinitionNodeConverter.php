<?php

namespace Nuwave\Lighthouse\Schema\Conversion;

use GraphQL\Type\Definition\Type;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\NamedTypeNode;
use Nuwave\Lighthouse\Schema\TypeRegistry;

class DefinitionNodeConverter
{
    /** @var TypeRegistry */
    protected $typeRegistry;

    /**
     * @param TypeRegistry $typeRegistry
     */
    public function __construct(TypeRegistry $typeRegistry)
    {
        $this->typeRegistry = $typeRegistry;
    }

    /**
     * Convert a definition node to an executable Type.
     *
     * @param mixed $node
     *
     * @return Type
     */
    public function toType($node): Type
    {
        return $this->convertWrappedDefinitionNode($node);
    }

    /**
     * Unwrap the node if needed and convert to type.
     *
     * @param mixed $node
     * @param array $wrappers
     *
     * @return Type
     */
    protected function convertWrappedDefinitionNode($node, array $wrappers = []): Type
    {
        // Recursively unwrap the type and save the wrappers
        if (NodeKind::NON_NULL_TYPE === $node->kind || NodeKind::LIST_TYPE === $node->kind) {
            $wrappers[] = $node->kind;

            return $this->convertWrappedDefinitionNode(
                $node->type,
                $wrappers
            );
        }

        // Re-wrap the type by applying the wrappers in the reversed order
        return collect($wrappers)
            ->reverse()
            ->reduce(
                function (Type $type, string $kind) {
                    if (NodeKind::NON_NULL_TYPE === $kind) {
                        return Type::nonNull($type);
                    } elseif (NodeKind::LIST_TYPE === $kind) {
                        return Type::listOf($type);
                    }

                    return $type;
                },
                $this->convertNamedTypeNode($node)
            );
    }

    /**
     * Converted named node to type.
     *
     * @param NamedTypeNode $node
     *
     * @return Type
     */
    protected function convertNamedTypeNode(NamedTypeNode $node): Type
    {
        $nodeName = $node->name->value;
        switch ($nodeName) {
            case 'ID':
                return Type::id();
            case 'Int':
                return Type::int();
            case 'Boolean':
                return Type::boolean();
            case 'Float':
                return Type::float();
            case 'String':
                return Type::string();
            default:
                return $this->typeRegistry->get($nodeName);
        }
    }
}
