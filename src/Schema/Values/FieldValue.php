<?php

namespace Nuwave\Lighthouse\Schema\Values;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\Directives\Fields\FieldResolver;
use Nuwave\Lighthouse\Schema\Resolvers\NodeResolver;

class FieldValue
{
    /**
     * Current type.
     *
     * @var \Closure
     */
    protected $type;

    /**
     * The underlying Field Definition.
     *
     * @var FieldDefinitionNode
     */
    protected $fieldDefinition;

    /**
     * The parent type in which the field is contained.
     *
     * @var TypeValue
     */
    protected $parentType;

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
     * Additional args to inject
     * into resolver.
     *
     * @var array
     */
    protected $args = [];

    /**
     * @param TypeValue           $parentType
     * @param FieldDefinitionNode $fieldDefinition
     */
    public function __construct($fieldDefinition, TypeValue $parentType)
    {
        $this->parentType = $parentType;
        $this->fieldDefinition = $fieldDefinition;
    }

    /**
     * Get the field resolver directive if it exists.
     *
     * @return FieldResolver|null
     */
    public function resolverDirective()
    {
        return graphql()->directives()->fieldResolver($this->fieldDefinition);
    }

    /**
     * Get a collection of field middleware directives.
     *
     * @return \Illuminate\Support\Collection
     */
    public function middlewareDirectives()
    {
        return graphql()->directives()->fieldMiddleware($this->fieldDefinition);
    }

    /**
     * Set current resolver.
     *
     * @param \Closure|null $resolver
     *
     * @return FieldValue
     */
    public function setResolver(\Closure $resolver = null)
    {
        $this->resolver = $resolver;

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
     * Set current complexity.
     *
     * @param \Closure $complexity
     *
     * @return self
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
     * Get an instance of the return type of the field.
     *
     * @return \Closure|Type
     */
    public function getReturnTypeInstance()
    {
        return NodeResolver::resolve($this->fieldDefinition->type);
    }

    /**
     * @return TypeValue
     */
    public function getParentType()
    {
        return $this->parentType;
    }

    /**
     * Get current field.
     *
     * @return FieldDefinitionNode
     */
    public function getFieldDefinition()
    {
        return $this->fieldDefinition;
    }

    /**
     * Get field resolver.
     *
     * @return \Closure
     */
    public function getResolver()
    {
        return $this->resolver;
    }

    /**
     * Get current description.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description ?: trim(str_replace("\n", '', $this->fieldDefinition->description));
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
        return $this->fieldDefinition->name->value;
    }

    /**
     * Get field's node name.
     *
     * @return string
     */
    public function getParentTypeName()
    {
        return $this->getParentType()->getName();
    }

    /**
     * Wrap resolver.
     *
     * @param \Closure $resolver
     *
     * @return \Closure
     */
    public function wrap(\Closure $resolver)
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
