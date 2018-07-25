<?php

namespace Nuwave\Lighthouse\Schema\Values;

use GraphQL\Type\Definition\Type;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\AST\FieldDefinitionNode;

class FieldValue
{
    /**
     * Current type.
     *
     * @var \Closure|Type
     */
    protected $type;

    /**
     * Current field.
     *
     * @var FieldDefinitionNode
     */
    protected $field;

    /**
     * Current node (type).
     *
     * @var NodeValue
     */
    protected $node;

    /**
     * Field resolver closure.
     *
     * @var \Closure
     */
    protected $resolver;

    /**
     * Field complexity.
     *
     * @var \Closure
     */
    protected $complexity;

    /**
     * Cache key should be private.
     *
     * @var bool
     */
    protected $privateCache = false;

    /**
     * Additional args to inject into resolver.
     *
     * @var array
     */
    protected $additionalArgs = [];

    /**
     * Create new field value instance.
     *
     * @param NodeValue           $node
     * @param FieldDefinitionNode $field
     */
    public function __construct(NodeValue $node, $field)
    {
        $this->node = $node;
        $this->field = $field;
    }

    /**
     * Set current type.
     *
     * @param \Closure|Type $type
     *
     * @return FieldValue
     */
    public function setType($type): FieldValue
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Set current resolver.
     *
     * @param \Closure|null $resolver
     *
     * @return FieldValue
     */
    public function setResolver(\Closure $resolver = null): FieldValue
    {
        $this->resolver = $resolver;

        return $this;
    }

    /**
     * Set current complexity.
     *
     * @param \Closure $complexity
     *
     * @return FieldValue
     */
    public function setComplexity(\Closure $complexity): FieldValue
    {
        $this->complexity = $complexity;

        return $this;
    }

    /**
     * Inject field argument.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return FieldValue
     */
    public function injectArg(string $key, $value): FieldValue
    {
        $this->additionalArgs = array_merge($this->additionalArgs, [
            $key => $value,
        ]);

        return $this;
    }

    /**
     * @return array
     */
    public function getAdditionalArgs(): array
    {
        return $this->additionalArgs;
    }

    /**
     * Get current type.
     *
     * @return \Closure|Type
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Get current node.
     *
     * @return NodeValue
     */
    public function getNode(): NodeValue
    {
        return $this->node;
    }

    /**
     * Get current field.
     *
     * @return FieldDefinitionNode
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * Get field resolver.
     *
     * @return \Closure
     */
    public function getResolver(): \Closure
    {
        return $this->resolver;
    }

    /**
     * Get current description.
     *
     * @return StringValueNode|null
     */
    public function getDescription()
    {
        return $this->field->description;
    }

    /**
     * Get current complexity.
     *
     * @return \Closure|null
     */
    public function getComplexity()
    {
        return $this->complexity;
    }


    /**
     * Get private cache flag.
     *
     * @param null $flag
     *
     * @return FieldValue|bool
     */
    public function isPrivateCache($flag = null)
    {
        if (null === $flag) {
            return $this->privateCache;
        }

        $this->privateCache = $flag;

        return $this;
    }

    /**
     * Get field name.
     *
     * @return string
     */
    public function getFieldName(): string
    {
        return $this->field->name->value;
    }

    /**
     * Get field's node name.
     *
     * @return string
     */
    public function getNodeName(): string
    {
        return $this->getNode()->getNodeName();
    }
}
