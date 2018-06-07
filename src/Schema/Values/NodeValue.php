<?php

namespace Nuwave\Lighthouse\Schema\Values;

use GraphQL\Language\AST\DefinitionNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Support\Collection;

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
     * @var DefinitionNode
     */
    protected $node;

    /**
     * Create new instance of node value.
     *
     * @param DefinitionNode $node
     */
    public function __construct(DefinitionNode $node)
    {
        $this->node = $node;
    }

    /**
     * Set current node instance.
     *
     * @param DefinitionNode $node
     *
     * @return self
     */
    public function setNode(DefinitionNode $node)
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
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get current node.
     *
     * @return DefinitionNode
     */
    public function getNode()
    {
        return $this->node;
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
}
