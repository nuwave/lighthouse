<?php

namespace Nuwave\Lighthouse\Schema\AST;

use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;
use GraphQL\Utils\AST;

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
     * @param array          $addition
     *
     * @return NodeList
     */
    public static function mergeNodeList($original, $addition)
    {
        if (! $original instanceof NodeList) {
            $original = new NodeList($original);
        }

        return $original->merge($addition);
    }

    /**
     * Clone definition node.
     *
     * @param Node $node
     *
     * @return Node
     */
    public static function cloneDefinition(Node $node)
    {
        return AST::fromArray($node->toArray(true));
    }
}
