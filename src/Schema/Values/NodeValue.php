<?php

namespace Nuwave\Lighthouse\Schema\Values;

use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Collection;

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
     * @return NodeValue
     * @deprecated Just use the constructor
     */
    public static function init(Node $node): NodeValue
    {
        return new static($node);
    }

    /**
     * Set current node instance.
     *
     * @param Node $node
     *
     * @return NodeValue
     */
    public function setNode(Node $node): NodeValue
    {
        $this->node = $node;

        return $this;
    }

    /**
     * Set type definition.
     *
     * @param Type $type
     *
     * @return NodeValue
     */
    public function setType(Type $type): NodeValue
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Set the current directive.
     *
     * @param DirectiveNode $directive
     *
     * @return NodeValue
     */
    public function setDirective(DirectiveNode $directive): NodeValue
    {
        $this->directive = $directive;

        return $this;
    }

    /**
     * Set the current namespace.
     *
     * @param string $namespace
     *
     * @return NodeValue
     */
    public function setNamespace(string $namespace): NodeValue
    {
        $this->namespace = $namespace;
    }

    /**
     * Get current node.
     *
     * @return Node
     */
    public function getNode(): Node
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
    public function getNamespace(string $class = null): string
    {
        return $class ? $this->namespace.'\\'.$class : $this->namespace;
    }

    /**
     * Get the name of the node.
     *
     * @return string
     */
    public function getNodeName(): string
    {
        return data_get($this->getNode(), 'name.value');
    }

    /**
     * Get fields for node.
     *
     * @return NodeList|array
     */
    public function getNodeFields()
    {
        return data_get($this->getNode(), 'fields', []);
    }

    /**
     * Get a collection of the names of all interfaces the node has.
     *
     * @return Collection
     */
    public function getInterfaceNames(): Collection
    {
        return collect($this->node->interfaces)
            ->map(function (NamedTypeNode $interface) {
                return $interface->name->value;
            });
    }

    /**
     * Check if node implements a interface.
     *
     * @param string $interfaceName
     *
     * @return bool
     */
    public function hasInterface(string $interfaceName): bool
    {
        return $this->getInterfaceNames()
            ->containsStrict($interfaceName);
    }
}
