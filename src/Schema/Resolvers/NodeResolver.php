<?php

namespace Nuwave\Lighthouse\Schema\Resolvers;

use GraphQL\Type\Definition\Type;
use GraphQL\Language\AST\NamedTypeNode;

class NodeResolver
{
    /**
     * Parse type from node.
     *
     * @param mixed $node
     *
     * @return \GraphQL\Type\Definition\Type
     */
    public static function resolve($node)
    {
        return (new static())->fromNode($node);
    }

    /**
     * Get type from node.
     *
     * @param mixed $node
     * @param array $wrappers
     *
     * @return mixed
     */
    public function fromNode($node, array $wrappers = [])
    {
        if ('NonNullType' === $node->kind) {
            return $this->fromNode(
                $node->type,
                array_merge($wrappers, ['NonNullType'])
            );
        } elseif ('ListType' === $node->kind) {
            return $this->fromNode(
                $node->type,
                array_merge($wrappers, ['ListType'])
            );
        }

        return collect($wrappers)
            ->reverse()
            ->reduce(function ($type, $kind) {
                if ('NonNullType' === $kind) {
                    return Type::nonNull($type);
                } elseif ('ListType' === $kind) {
                    return Type::listOf($type);
                }

                return $type;
            }, $this->extractTypeFromNode($node));
    }

    /**
     * Extract type from node definition.
     *
     * @param mixed $node
     *
     * @return Type|null
     */
    protected function extractTypeFromNode($node)
    {
        return $node instanceof NamedTypeNode
            ? $this->convertNamedType($node)
            : null;
    }

    /**
     * Converted named node to type.
     *
     * @param NamedTypeNode $node
     *
     * @return Type
     */
    protected function convertNamedType(NamedTypeNode $node): Type
    {
        switch ($node->name->value) {
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
                return graphql()->types()->get($node->name->value);
        }
    }
}
