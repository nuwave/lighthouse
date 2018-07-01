<?php

namespace Nuwave\Lighthouse\Schema\Values;

use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Type\Definition\Type;

class ArgumentValue
{
    /**
     * Current input argument.
     *
     * @var InputValueDefinitionNode
     */
    protected $arg;

    /**
     * Current field.
     *
     * @var FieldValue
     */
    protected $field;
    /**
     * Set current arg type.
     *
     * @var Type
     */
    protected $type;
    /**
     * The resolver function for the arg.
     *
     * @var \Closure
     */
    protected $resolver;

    /**
     * Validation rules.
     *
     * @var array
     */
    protected $rules;

    /**
     * Messages for the validation rules.
     *
     * @var array
     */
    protected $messages;

    /**
     * Create a new argument value instance.
     *
     * @param FieldValue $field
     * @param InputValueDefinitionNode $arg
     */
    public function __construct(FieldValue $field, InputValueDefinitionNode $arg)
    {
        $this->field = $field;
        $this->arg = $arg;
    }

    /**
     * @return array|null
     */
    public function getRules()
    {
        return $this->rules;
    }

    /**
     * @param array $rules
     *
     * @return ArgumentValue
     */
    public function setRules(array $rules): ArgumentValue
    {
        $this->rules = $rules;

        return $this;
    }

    /**
     * @return array|null
     */
    public function getMessages()
    {
        return $this->messages;
    }

    /**
     * @param array $messages
     *
     * @return ArgumentValue
     */
    public function setMessages(array $messages): ArgumentValue
    {
        $this->messages = $messages;

        return $this;
    }

    /**
     * Get current field.
     *
     * @return FieldValue
     */
    public function getField(): FieldValue
    {
        return $this->field;
    }

    /**
     * Get the current argument type.
     *
     * @return Type
     */
    public function getType(): Type
    {
        return $this->type;
    }

    /**
     * Get current argument type.
     *
     * @param Type $type
     *
     * @return ArgumentValue
     */
    public function setType(Type $type): ArgumentValue
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get the associated resolver function.
     *
     * @return \Closure|null
     */
    public function getResolver()
    {
        return $this->resolver;
    }

    /**
     * Set a argument resolver.
     *
     * @param \Closure $resolver
     *
     * @return ArgumentValue
     */
    public function setResolver(\Closure $resolver): ArgumentValue
    {
        $this->resolver = $resolver;

        return $this;
    }

    /**
     * Get argument name.
     *
     * @return string
     */
    public function getArgName(): string
    {
        return $this->getArg()->name->value;
    }

    /**
     * Get current argument.
     *
     * @return InputValueDefinitionNode
     */
    public function getArg(): InputValueDefinitionNode
    {
        return $this->arg;
    }

    /**
     * Set current argument.
     *
     * @param InputValueDefinitionNode $arg
     *
     * @return ArgumentValue
     */
    public function setArg(InputValueDefinitionNode $arg): ArgumentValue
    {
        $this->arg = $arg;

        return $this;
    }
}
