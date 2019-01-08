<?php

namespace Nuwave\Lighthouse\Schema\Values;

use GraphQL\Type\Definition\Type;
use GraphQL\Language\AST\TypeDefinitionNode;

class NodeValue
{
    /**
     * Current GraphQL type.
     *
     * @var \GraphQL\Type\Definition\Type
     */
    protected $type;

    /**
     * Current definition node.
     *
     * @var \GraphQL\Language\AST\TypeDefinitionNode
     */
    protected $typeDefinition;

    /**
     * Cache key for this type.
     *
     * @var string
     */
    protected $cacheKey;

    /**
     * @param  TypeDefinitionNode  $typeDefinition
     */
    public function __construct(TypeDefinitionNode $typeDefinition)
    {
        $this->typeDefinition = $typeDefinition;
    }

    /**
     * Get resolved type.
     *
     * @return \GraphQL\Type\Definition\Type|null
     */
    public function getType(): ?Type
    {
        return $this->type;
    }

    /**
     * Set the executable type.
     *
     * @param  Type  $type
     *
     * @return self
     */
    public function setType(Type $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get the name of the node.
     *
     * @return string
     */
    public function getTypeDefinitionName(): string
    {
        return data_get($this->getTypeDefinition(), 'name.value');
    }

    /**
     * Get the underlying type definition.
     *
     * @return \GraphQL\Language\AST\TypeDefinitionNode
     */
    public function getTypeDefinition(): TypeDefinitionNode
    {
        return $this->typeDefinition;
    }

    /**
     * Get the underlying type definition fields.
     *
     * @return \GraphQL\Language\AST\NodeList|array
     */
    public function getTypeDefinitionFields()
    {
        return data_get($this->typeDefinition, 'fields', []);
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
     * Set node cache key.
     *
     * @param  string  $key
     *
     * @return \GraphQL\Language\AST\NodeValue
     */
    public function setCacheKey(string $key = null): self
    {
        $this->cacheKey = $key;

        return $this;
    }
}
