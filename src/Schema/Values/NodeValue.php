<?php

namespace Nuwave\Lighthouse\Schema\Values;

use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\Type;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\NamedTypeNode;

class NodeValue
{
    /**
     * Current GraphQL type.
     *
     * @var Type
     */
    protected $type;

    /**
     * Current definition node.
     *
     * @var Node
     */
    protected $node;

    /**
     * Node directive.
     *
     * @var DirectiveNode
     */
    protected $directive;

    /**
     * Current namespace.
     *
     * @var string
     */
    protected $namespace;

    /**
     * Cache key for node.
     *
     * @var string
     */
    protected $cacheKey;

    /**
     * Create new instance of node value.
     *
     * @param Node $node
     */
    public function __construct(Node $node)
    {
        $this->node = $node;
    }

    /**
     * Create new instance of node value.
     *
     * @param Node $node
     *
     * @return NodeValue
     */
    public static function init(Node $node)
    {
        return new static($node);
    }

    /**
     * Set current node instance.
     *
     * @param Node $node
     *
     * @return self
     */
    public function setNode(Node $node)
    {
        $this->node = $node;

        return $this;
    }

    /**
     * Set type definition.
     *
     * @param Type $type
     *
     * @return self
     */
    public function setType(Type $type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Set the current directive.
     *
     * @param DirectiveNode $directive
     */
    public function setDirective(DirectiveNode $directive)
    {
        $this->directive = $directive;
    }

    /**
     * Set the current namespace.
     *
     * @param string $namespace
     */
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
    }

    /**
     * Set node cache key.
     *
     * @param string $key
     */
    public function setCacheKey($key)
    {
        $this->cacheKey = $key;
    }

    /**
     * Get current node.
     *
     * @return Node
     */
    public function getNode()
    {
        return $this->node;
    }

    /**
     * Get current directive.
     *
     * @return DirectiveNode|null
     */
    public function getDirective()
    {
        return $this->directive;
    }

    /**
     * Get resolved type.
     *
     * @return Type|null
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Get current namespace.
     *
     * @param string $class
     *
     * @return string
     */
    public function getNamespace($class = null)
    {
        return $class ? $this->namespace.'\\'.$class : $this->namespace;
    }

    /**
     * Get the name of the node.
     *
     * @return string
     */
    public function getNodeName()
    {
        return data_get($this->getNode(), 'name.value');
    }

    /**
     * Get fields for node.
     *
     * @return array
     */
    public function getNodeFields()
    {
        return data_get($this->getNode(), 'fields', []);
    }

    /**
     * Get a collection of the names of all interfaces the node has.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getInterfaceNames()
    {
        return collect($this->node->interfaces)
            ->map(function (NamedTypeNode $interface) {
                return $interface->name->value;
            });
    }

    /**
     * Get node's cache key.
     *
     * @return string|null
     */
    public function getCacheKey()
    {
        return $this->cacheKey;
    }

    /**
     * Check if node implements a interface.
     *
     * @param string $interfaceName
     *
     * @return bool
     */
    public function hasInterface($interfaceName)
    {
        return $this->getInterfaceNames()
            ->containsStrict($interfaceName);
    }
}
