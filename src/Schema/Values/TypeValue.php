<?php

namespace Nuwave\Lighthouse\Schema\Values;

use GraphQL\Language\AST\DefinitionNode;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Type\Definition\Type;

class TypeValue
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
    protected $definition;

    /**
     * Create new instance of node value.
     *
     * @param DefinitionNode $definition
     */
    public function __construct(DefinitionNode $definition)
    {
        $this->definition = $definition;
    }

    /**
     * Set current node instance.
     *
     * @param DefinitionNode $definition
     *
     * @return self
     */
    public function setDefinition(DefinitionNode $definition)
    {
        $this->definition = $definition;

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
    public function getDefinition()
    {
        return $this->definition;
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
    public function getName()
    {
        return data_get($this->getDefinition(), 'name.value');
    }

    /**
     * Get fields for node.
     *
     * @return array
     */
    public function getFields()
    {
        return data_get($this->getDefinition(), 'fields', []);
    }

    /**
     * Get a collection of the names of all interfaces the node has.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getInterfaceNames()
    {
        return collect($this->definition->interfaces)
            ->map(function (NamedTypeNode $interface) {
                return $interface->name->value;
            });
    }
}
