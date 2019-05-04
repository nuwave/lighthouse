<?php

namespace Nuwave\Lighthouse\Schema\AST;

use ArrayAccess;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;
use Serializable;

class NodeListMap implements ArrayAccess, Serializable
{
    /** @var NodeList[] */
    private $nodeLists;

    /**
     * @param Node[]|mixed[] $nodes
     */
    public function __construct(array $nodes)
    {
        $this->nodeLists = $nodes;
    }

    /**
     * @param mixed $offset
     *
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->nodeLists[$offset]);
    }

    /**
     * @param string $offset
     *
     * @return mixed
     */
    public function offsetGet($offset): NodeList
    {
        $item = $this->nodeLists[$offset];

        if (is_array($item)) {
            $this->nodeLists[$offset] = $item = new NodeList($item);
        }

        return $item;
    }

    /**
     * @param string $offset
     * @param NodeList $value
     */
    public function offsetSet($offset, $value): void
    {
        if (is_array($value)) {
            $value = new NodeList($value);
        }

        $this->nodeLists[$offset] = $value;
    }

    /**
     * @param string $offset
     */
    public function offsetUnset($offset)
    {
        unset($this->nodeLists[$offset]);
    }

    public function serialize(): string
    {

    }

    /**
     * Constructs the object
     * @link https://php.net/manual/en/serializable.unserialize.php
     * @param string $serialized <p>
     * The string representation of the object.
     * </p>
     * @return void
     * @since 5.1.0
     */
    public function unserialize($serialized)
    {
        // TODO: Implement unserialize() method.
    }
}
