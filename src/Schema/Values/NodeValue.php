<?php

namespace Nuwave\Lighthouse\Schema\Values;

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
     * The underlying type definition node.
     *
     * @var \GraphQL\Language\AST\TypeDefinitionNode
     */
    protected $typeDefinition;

    /**
     * Cache key for this type.
     *
     * @var string|null
     */
    protected $cacheKey;

    /**
     * @param  \GraphQL\Language\AST\TypeDefinitionNode  $typeDefinition
     * @return void
     */
    public function __construct(TypeDefinitionNode $typeDefinition)
    {
        $this->typeDefinition = $typeDefinition;
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
    public function getCacheKey(): ?string
    {
        return $this->cacheKey;
    }

    /**
     * Set node cache key.
     *
     * @param  string|null  $key
     * @return $this
     */
    public function setCacheKey(string $key = null): self
    {
        $this->cacheKey = $key;

        return $this;
    }
}
