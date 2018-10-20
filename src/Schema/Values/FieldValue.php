<?php

namespace Nuwave\Lighthouse\Schema\Values;

use GraphQL\Type\Definition\Type;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\Conversion\DefinitionNodeConverter;

class FieldValue
{
    /**
     * Current type.
     *
     * @var \Closure|Type
     */
    protected $type;

    /**
     * @todo remove InputValueDefinitionNode once it no longer reuses this class.
     *
     * @var FieldDefinitionNode|InputValueDefinitionNode
     */
    protected $field;

    /**
     * The parent type of the field.
     *
     * @var NodeValue
     */
    protected $node;

    /**
     * The actual field resolver.
     *
     * @var \Closure|null
     */
    protected $resolver;

    /**
     * A closure that determines the complexity of executing the field.
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
     * @param NodeValue           $parent
     * @todo remove InputValueDefinitionNode once it no longer reuses this class.
     * @param FieldDefinitionNode|InputValueDefinitionNode $field
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
     * Define a closure that is used to determine the complexity of the field.
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
        $this->additionalArgs = array_merge(
            $this->additionalArgs,
            [$key => $value]
        );

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
     * @todo remove InputValueDefinitionNode once it no longer reuses this class.
     *
     * @return FieldDefinitionNode|InputValueDefinitionNode
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
