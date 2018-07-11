<?php

namespace Nuwave\Lighthouse\Schema\Values;

use GraphQL\Type\Definition\Type;
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
     * Current description.
     *
     * @var string
     */
    protected $description;

    /**
     * Field complexity.
     *
     * @var \Closure
     */
    protected $complexity;

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
     * @param string              $description
     */
    public function __construct(NodeValue $node, $field, string $description = '')
    {
        $this->node = $node;
        $this->field = $field;
        $this->description = $description;
    }

    /**
     * Initialize new field value.
     *
     * @param NodeValue           $node
     * @param FieldDefinitionNode $field
     * @param string              $description
     *
     * @return FieldValue
     */
    public static function init(NodeValue $node, $field, string $description = ''): FieldValue
    {
        return new static($node, $field, $description);
    }

    /**
     * Set current description.
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
     * Set current description.
     *
     * @param string $description
     *
     * @return FieldValue
     */
    public function setDescription(string $description): FieldValue
    {
        $this->description = $description;

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
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description
            ?: trim(str_replace("\n", '', $this->getField()->description));
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
     * Get field name.
     *
     * @return string
     */
    public function getFieldName(): string
    {
        return $this->getField()->name->value;
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
