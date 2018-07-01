<?php

namespace Nuwave\Lighthouse\Schema\AST;

use GraphQL\Utils\AST;
use GraphQL\Language\AST\Node;
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
     * This function will merge two lists uniquely by name. Fields will
     * be removed from the original list if the name exists in both lists.
     *
     * @param NodeList|array $original
     * @param array          $addition
     *
     * @return NodeList
     */
    public static function mergeUniqueNodeList($original, $addition)
    {
        $newFields = collect($addition)->pluck('name.value')->filter()->all();
        $filteredList = collect($original)->filter(function ($field) use ($newFields) {
            return ! in_array(data_get($field, 'name.value'), $newFields);
        })->values()->all();

        return self::mergeNodeList($filteredList, $addition);
    }

    /**
     * Create a clone of the original node.
     *
     * @param Node $node
     *
     * @return Node
     */
    public static function cloneNode(Node $node)
    {
        return AST::fromArray($node->toArray(true));
    }
}
