<?php

namespace Nuwave\Lighthouse\Schema\Values;

use Closure;
use GraphQL\Language\AST\DirectiveNode as Directive;
use GraphQL\Language\AST\FieldDefinitionNode as Field;
use GraphQL\Language\AST\InputValueDefinitionNode as Argument;

class ArgumentValue
{
    /**
     * Value context.
     *
     * @var mixed
     */
    protected $context;

    /**
     * Current input argument.
     *
     * @var Argument
     */
    protected $arg;

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
     * Current value.
     *
     * @var array
     */
    protected $value;

    /**
     * Create a new argument value instance.
     *
     * @param Field $field
     * @param array $value
     */
    public function __construct(Field $field, array $value)
    {
        $this->field = $field;
        $this->value = $value;
    }

    /**
     * Initialize new argument value.
     *
     * @param Field $field
     * @param array $value
     *
     * @return self
     */
    public static function init(Field $field, $value)
    {
        return new static($field, ['type' => $value]);
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
     * Set current argument.
     *
     * @param Argument $arg
     *
     * @return self
     */
    public function setArg(Argument $arg)
    {
        $this->arg = $arg;

        return $this;
    }

    /**
     * Set the current value.
     *
     * @param array $value
     */
    public function setValue(array $value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Set directive for middleware.
     *
     * @param string $middleware
     *
     * @return self
     */
    public function setMiddlewareDirective($middleware)
    {
        $this->directive = collect($this->arg->directives)
            ->first(function (Directive $directive) use ($middleware) {
                return $directive->name->value === $middleware;
            });

        return $this;
    }

    /**
     * Set a argument resolver.
     *
     * @param Closure $resolver
     *
     * @return self
     */
    public function setResolver(Closure $resolver)
    {
        $current = $this->getValue();
        $current['resolve'] = $resolver;

        return $this->setValue($current);
    }

    /**
     * Get current argument.
     *
     * @return Argument
     */
    public function getArg()
    {
        return $this->arg;
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
     * Get the current value.
     *
     * @return array
     */
    public function getValue()
    {
        return $this->value;
    }
}
