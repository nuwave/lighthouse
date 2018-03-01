<?php

namespace Nuwave\Lighthouse\Schema\Values;

use GraphQL\Language\AST\FieldDefinitionNode as Field;
use GraphQL\Language\AST\Node;

class FieldValue
{
    /**
     * Current type.
     *
     * @var mixed
     */
    protected $type;

    /**
     * Current field.
     *
     * @var Field
     */
    protected $field;

    /**
     * Current node (type).
     *
     * @var Node
     */
    protected $node;

    /**
     * Current description.
     *
     * @var string
     */
    protected $description;

    /**
     * Create new field value instance.
     *
     * @param mixed  $type
     * @param Node   $node
     * @param Field  $field
     * @param string $description
     */
    public function __construct($type, Node $node, Field $field, $description = '')
    {
        $this->type = $type;
        $this->node = $node;
        $this->field = $field;
        $this->description = $description;
    }

    /**
     * Initialize new field value.
     *
     * @param mixed  $type
     * @param Node   $node
     * @param Field  $field
     * @param string $description
     *
     * @return self
     */
    public static function init($type, Node $node, Field $field, $description = '')
    {
        return new static($type, $node, $field, $description);
    }

    /**
     * Set current description.
     *
     * @param mixed $type
     *
     * @return self
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Set current description.
     *
     * @param string $description
     *
     * @return self
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get current type.
     *
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Get current node.
     *
     * @return Node
     */
    public function getNode()
    {
        return $this->node;
    }

    /**
     * Get current field.
     *
     * @return Field
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * Get current description.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }
}
