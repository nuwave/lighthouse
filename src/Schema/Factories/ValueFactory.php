<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Schema\Values\CacheValue;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use GraphQL\Language\AST\InputValueDefinitionNode;

class ValueFactory
{
    /**
     * Node value resolver.
     *
     * @var \Closure
     */
    protected $node;

    /**
     * Field value resolver.
     *
     * @var \Closure
     */
    protected $field;

    /**
     * Argument value resolver.
     *
     * @var \Closure
     */
    protected $arg;

    /**
     * Cache value resolver.
     *
     * @var \Closure
     */
    protected $cache;

    /**
     * Set node value instance resolver.
     *
     * @param \Closure $resolver
     *
     * @return ValueFactory
     */
    public function nodeResolver(\Closure $resolver): ValueFactory
    {
        $this->node = $resolver;

        return $this;
    }

    /**
     * Set field value instance resolver.
     *
     * @param \Closure $resolver
     *
     * @return ValueFactory
     */
    public function fieldResolver(\Closure $resolver): ValueFactory
    {
        $this->field = $resolver;

        return $this;
    }

    /**
     * Set arg value instance resolver.
     *
     * @param \Closure $resolver
     *
     * @return ValueFactory
     */
    public function argResolver(\Closure $resolver): ValueFactory
    {
        $this->arg = $resolver;

        return $this;
    }

    /**
     * Set cache value instance resolver.
     *
     * @param \Closure $resolver
     *
     * @return ValueFactory
     */
    public function cacheResolver(\Closure $resolver): ValueFactory
    {
        $this->cache = $resolver;

        return $this;
    }

    /**
     * Get value for node.
     *
     * @param TypeDefinitionNode $node
     *
     * @return NodeValue
     */
    public function node(TypeDefinitionNode $node): NodeValue
    {
        return $this->node
            ? call_user_func($this->node, $node)
            : new NodeValue($node);
    }

    /**
     * Get value for field.
     *
     * @param NodeValue $nodeValue
     * @todo remove InputValueDefinitionNode once it no longer reuses this class.
     * @param FieldDefinitionNode|InputValueDefinitionNode     $field
     *
     * @return FieldValue
     */
    public function field(NodeValue $nodeValue, $field): FieldValue
    {
        return $this->field
            ? call_user_func($this->field, $nodeValue, $field)
            : new FieldValue($nodeValue, $field);
    }

    /**
     * Get value for argument.
     *
     * @param FieldValue $fieldValue
     * @param mixed      $arg
     *
     * @return ArgumentValue
     */
    public function arg(FieldValue $fieldValue, $arg): ArgumentValue
    {
        return $this->arg
            ? call_user_func($this->arg, $fieldValue, $arg)
            : new ArgumentValue($fieldValue, $arg);
    }

    /**
     * Create cache value for field.
     *
     * @param array $arguments
     *
     * @return CacheValue
     */
    public function cache(array $arguments): CacheValue
    {
        return $this->cache
            ? call_user_func($this->cache, $arguments)
            : new CacheValue($arguments);
    }
}
