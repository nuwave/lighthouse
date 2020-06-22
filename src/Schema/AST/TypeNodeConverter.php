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
     * @return mixed The executable type.
     */
    public function convert(TypeNode $node)
    {
        return $this->convertWrappedTypeNode($node);
    }

    /**
     * Convert an AST type and apply wrapping types.
     *
     * @param  string[]  $wrappers
     * @return mixed The wrapped type.
     */
    protected function convertWrappedTypeNode(TypeNode $node, array $wrappers = [])
    {
        // Recursively unwrap the type and save the wrappers
        $nodeKind = $node->kind;
        if (in_array($nodeKind, [NodeKind::NON_NULL_TYPE, NodeKind::LIST_TYPE])) {
            /** @var \GraphQL\Language\AST\NonNullTypeNode|\GraphQL\Language\AST\ListTypeNode $node */
            $wrappers[] = $nodeKind;

            return $this->convertWrappedTypeNode( // @phpstan-ignore-line TODO remove when upgrading graphql-php
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
                    if ($kind === NodeKind::NON_NULL_TYPE) {
                        return $this->nonNull($type);
                    }

                    if ($kind === NodeKind::LIST_TYPE) {
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
     * @param  mixed  $type The type to wrap.
     * @return mixed The type wrapped with non-null.
     */
    abstract protected function nonNull($type);

    /**
     * Wrap or mark the type as a list.
     *
     * @param  mixed  $type The type to wrap.
     * @return mixed The type wrapped as a list.
     */
    abstract protected function listOf($type);

    /**
     * Get the named type for the given node name.
     *
     * @return mixed Representation of the type with the given name.
     */
    abstract protected function namedType(string $nodeName);
}
