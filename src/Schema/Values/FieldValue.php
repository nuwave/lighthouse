<?php

namespace Nuwave\Lighthouse\Schema\Values;

use Closure;
use GraphQL\Language\AST\DirectiveNode as Directive;
use GraphQL\Language\AST\FieldDefinitionNode as Field;
use GraphQL\Language\AST\Node;

class FieldValue
{
    /**
     * Value context.
     *
     * @var mixed
     */
    protected $context;

    /**
     * Current directive.
     *
     * @var Directive
     */
    protected $directive;

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
     * Current value.
     *
     * @var \Closure
     */
    protected $resolver;

    /**
     * Create new field value instance.
     *
     * @param Node    $node
     * @param Closure $resolver
     */
    public function __construct(Node $node, Closure $resolver)
    {
        $this->node = $node;
        $this->resolver = $resolver;
    }

    /**
     * Initialize new field value.
     *
     * @param Node    $node
     * @param Closure $resolver
     *
     * @return self
     */
    public static function init(Node $node, Closure $resolver)
    {
        return new static($node, $resolver);
    }

    /**
     * Set current resolver.
     *
     * @param Closure $resolver
     */
    public function setResolver(Closure $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Set current field.
     *
     * @param Field $field
     *
     * @return self
     */
    public function setField(Field $field)
    {
        $this->field = $field;

        return $this;
    }

    /**
     * Set current directive.
     *
     * @param Directive $directive
     *
     * @return self
     */
    public function setDirective(Directive $directive)
    {
        $this->directive = $directive;

        return $this;
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
     * Get current directive.
     *
     * @return Directive
     */
    public function getDirective()
    {
        return $this->directive;
    }

    /**
     * Get current resolver.
     *
     * @return Closure
     */
    public function getResolver()
    {
        return $this->resolver;
    }
}
