<?php

namespace Nuwave\Lighthouse\Schema\Values;

use GraphQL\Language\AST\FieldDefinitionNode as Field;
use GraphQL\Language\AST\Node;

class FieldValue
{
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
     * Create new field value instance.
     *
     * @param Node  $node
     * @param Field $field
     */
    public function __construct(Node $node, Field $field)
    {
        $this->node = $node;
        $this->field = $field;
    }

    /**
     * Initialize new field value.
     *
     * @param Node  $node
     * @param Field $field
     *
     * @return self
     */
    public static function init(Node $node, Field $field)
    {
        return new static($node, $field);
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
}
