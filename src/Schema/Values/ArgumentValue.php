<?php

namespace Nuwave\Lighthouse\Schema\Values;

use Closure;
use GraphQL\Language\AST\DirectiveNode as Directive;
use GraphQL\Language\AST\FieldDefinitionNode as Field;
use GraphQL\Language\AST\InputValueDefinitionNode as Argument;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\Values\FieldValue;

class ArgumentValue
{
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
     * @var FieldValue
     */
    protected $field;

    /**
     * Current value.
     *
     * @var array
     */
    protected $value;

    /**
     * Set current arg type.
     *
     * @var Type
     */
    protected $type;

    /**
     * Create a new argument value instance.
     *
     * @param FieldValue $field
     * @param Argument   $arg
     */
    public function __construct(FieldValue $field, Argument $arg)
    {
        $this->field = $field;
        $this->arg = $arg;
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
     * Get current argument type.
     *
     * @param Type $type
     *
     * @return self
     */
    public function setType(Type $type)
    {
        $this->type = $type;

        $value = $this->getValue();
        $value['type'] = $type;

        return $this->setValue($value);
    }

    /**
     * Set the current value.
     *
     * @param array $value
     *
     * @return self
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
     * Get current field.
     *
     * @return FieldValue
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
     * Get the current argument type.
     *
     * @return Type
     */
    public function getType()
    {
        return $this->type;
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

    /**
     * Get argument name.
     *
     * @return string
     */
    public function getArgName()
    {
        return $this->getArg()->name->value;
    }
}
