<?php

namespace Nuwave\Lighthouse\Schema\AST;

use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\TypeNode;
use Illuminate\Support\Collection;

abstract class TypeNodeConverter
{
    /**
     * Convert an AST type to an executable type.
     *
     * @param  \GraphQL\Language\AST\TypeNode&\GraphQL\Language\AST\Node  $node
     *
     * @return mixed the executable type
     */
    public function convert(TypeNode $node)
    {
        return $this->convertWrappedTypeNode($node);
    }

    /**
     * Convert an AST type and apply wrapping types.
     *
     * @param  \GraphQL\Language\AST\TypeNode&\GraphQL\Language\AST\Node  $node
     * @param  array<string>  $wrappers
     *
     * @return mixed the wrapped type
     */
    protected function convertWrappedTypeNode(TypeNode $node, array $wrappers = [])
    {
        // Recursively unwrap the type and save the wrappers
        $nodeKind = $node->kind;
        if (in_array($nodeKind, [NodeKind::NON_NULL_TYPE, NodeKind::LIST_TYPE])) {
            /** @var \GraphQL\Language\AST\NonNullTypeNode|\GraphQL\Language\AST\ListTypeNode $node */
            $wrappers[] = $nodeKind;

            return $this->convertWrappedTypeNode(
                $node->type,
                $wrappers
            );
        }
        /** @var \GraphQL\Language\AST\NamedTypeNode $node */

        // Re-wrap the type by applying the wrappers in the reversed order
        return (new Collection($wrappers))
            ->reverse()
            ->reduce(
                function ($type, string $kind) {
                    if (NodeKind::NON_NULL_TYPE === $kind) {
                        return $this->nonNull($type);
                    }

                    if (NodeKind::LIST_TYPE === $kind) {
                        return $this->listOf($type);
                    }

                    return $type;
                },
                $this->namedType($node->name->value)
            );
    }

    /**
     * Wrap or mark the type as non-null.
     *
     * @param  mixed  $type  the type to wrap
     *
     * @return mixed the type wrapped with non-null
     */
    abstract protected function nonNull($type);

    /**
     * Wrap or mark the type as a list.
     *
     * @param  mixed  $type  the type to wrap
     *
     * @return mixed the type wrapped as a list
     */
    abstract protected function listOf($type);

    /**
     * Get the named type for the given node name.
     *
     * @return mixed representation of the type with the given name
     */
    abstract protected function namedType(string $nodeName);
}
