<?php

namespace Nuwave\Lighthouse\Schema\AST;

use GraphQL\Language\AST\NodeList;

class ASTHelper
{
    /**
     * This function exists as a workaround for an issue within webonyx/graphql-php.
     *
     * The problem is that lists of definitions are usually NodeList objects - except
     * when the list is empty, then it is []. This function corrects that inconsistency
     * and allows the rest of our code to not worry about it until it is fixed.
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
}
