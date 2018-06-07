<?php

namespace Nuwave\Lighthouse\Schema\Values;

use Closure;
use GraphQL\Language\AST\FieldDefinitionNode as Field;
use GraphQL\Type\Definition\Type;

class FieldValue
{
    /**
     * Current type.
     *
     * @var Closure
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
     * Current namespace.
     *
     * @var string
     */
    protected $namespace;

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
     * Additional args to inject
     * into resolver.
     *
     * @var array
     */
    protected $args = [];

    /**
     * Create new field value instance.
     *
     * @param NodeValue $node
     * @param Field     $field
     * @param string    $description
     */
    public function __construct(NodeValue $node, $field, $description = '')
    {
        $this->node = $node;
        $this->field = $field;
        $this->description = $description;
    }

    /**
     * Initialize new field value.
     *
     * @param NodeValue $node
     * @param Field     $field
     * @param string    $description
     *
     * @return self
     */
    public static function init(NodeValue $node, Field $field, $description = '')
    {
        return new static($node, $field, $description);
    }

    /**
     * Set current description.
     *
     * @param Closure|Type $type
     *
     * @return self
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Set current resolver.
     *
     * @param Closure|null $resolver
     *
     * @return FieldValue
     */
    public function setResolver(Closure $resolver = null)
    {
        $this->resolver = $resolver;

        return $this;
    }

    /**
     * Set the current namespace.
     *
     * @param string $namespace
     */
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
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
     * Set current complexity.
     *
     * @param \Closure $complexity
     */
    public function setComplexity($complexity)
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
     * @return self
     */
    public function injectArg($key, $value)
    {
        $this->args = array_merge($this->args, [
            $key => $value,
        ]);

        return $this;
    }

    /**
     * Get current type.
     *
     * @return Closure|Type
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
     * Get field resolver.
     *
     * @return Closure
     */
    public function getResolver()
    {
        return $this->resolver;
    }

    /**
     * Get current namespace.
     *
     * @param string $class
     *
     * @return string
     */
    public function getNamespace($class = null)
    {
        return $class ? $this->namespace.'\\'.$class : $this->namespace;
    }

    /**
     * Get current description.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description ?: trim(str_replace("\n", '', $this->getField()->description));
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
    public function getFieldName()
    {
        return $this->getField()->name->value;
    }

    /**
     * Get field's node name.
     *
     * @return string
     */
    public function getNodeName()
    {
        return $this->getNode()->getNodeName();
    }

    /**
     * Wrap resolver.
     *
     * @param Closure $resolver
     *
     * @return Closure
     */
    public function wrap(Closure $resolver)
    {
        if (empty($this->args)) {
            return $resolver;
        }

        return function () use ($resolver) {
            $args = func_get_args();
            $args[1] = array_merge($args[1], $this->args);

            return call_user_func_array($resolver, $args);
        };
    }
}
