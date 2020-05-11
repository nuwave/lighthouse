<?php

namespace Nuwave\Lighthouse\Schema\Values;

use GraphQL\Language\AST\TypeDefinitionNode;

class TypeValue
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

    public function __construct(TypeDefinitionNode $typeDefinition)
    {
        $this->typeDefinition = $typeDefinition;
    }

    /**
     * Get the name of the node.
     */
    public function getTypeDefinitionName(): string
    {
        return data_get($this->getTypeDefinition(), 'name.value');
    }

    /**
     * Get the underlying type definition.
     */
    public function getTypeDefinition(): TypeDefinitionNode
    {
        return $this->typeDefinition;
    }

    /**
     * Get the underlying type definition fields.
     *
     * @deprecated
     * @return iterable<\GraphQL\Language\AST\FieldDefinitionNode>
     */
    public function getTypeDefinitionFields()
    {
        return data_get($this->typeDefinition, 'fields', []);
    }

    /**
     * Get node's cache key.
     */
    public function getCacheKey(): ?string
    {
        return $this->cacheKey;
    }

    /**
     * Set node cache key.
     *
     * @return $this
     */
    public function setCacheKey(string $key = null): self
    {
        $this->cacheKey = $key;

        return $this;
    }
}
