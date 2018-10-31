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
     * An instance of the type that this field returns.
     *
     * @var Type|null
     */
    protected $returnType;

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
    protected $parent;

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
    public function __construct(NodeValue $parent, $field)
    {
        $this->parent = $parent;
        $this->field = $field;
    }

    /**
     * Overwrite the current/default resolver.
     *
     * @param \Closure $resolver
     *
     * @return FieldValue
     */
    public function setResolver(\Closure $resolver): FieldValue
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
     * Get an instance of the return type of the field.
     *
     * @return Type
     */
    public function getReturnType(): Type
    {
        if(! isset($this->returnType)){
            $this->returnType = resolve(DefinitionNodeConverter::class)->toType(
                $this->field->type
            );
        }

        return $this->returnType;
    }

    /**
     * @return NodeValue
     */
    public function getParent(): NodeValue
    {
        return $this->parent;
    }

    /**
     * @return string
     */
    public function getParentName(): string
    {
        return $this->getParent()->getNodeName();
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
        if(!isset($this->resolver)){
            $this->resolver = $this->defaultResolver();
        }

        return $this->resolver;
    }

    /**
     * Get default field resolver.
     *
     * @throws DefinitionException
     *
     * @return \Closure
     */
    protected function defaultResolver(): \Closure
    {
        if($namespace = $this->getDefaultNamespaceForParent()){
            return construct_resolver(
                $namespace . '\\' . studly_case($this->getFieldName()),
                'resolve'
            );
        }

        // TODO convert this back once we require PHP 7.1
        // return \Closure::fromCallable(
        //     [\GraphQL\Executor\Executor::class, 'defaultFieldResolver']
        // );
        return function() {
            return \GraphQL\Executor\Executor::defaultFieldResolver(...func_get_args());
        };
    }

    /**
     * If a default namespace exists for the parent type, return it.
     *
     * @return string|null
     */
    public function getDefaultNamespaceForParent()
    {
        switch ($this->getParentName()) {
            case 'Mutation':
                return config('lighthouse.namespaces.mutations');
            case 'Query':
                return config('lighthouse.namespaces.queries');
            default:
                return null;
        }
    }

    /**
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
     * @return string
     */
    public function getFieldName(): string
    {
        return $this->field->name->value;
    }

    /**
     * @return NodeValue
     * @deprecated
     */
    public function getNode(): NodeValue
    {
        return $this->getParent();
    }

    /**
     * Get field's node name.
     *
     * @return string
     * @deprecated
     */
    public function getNodeName(): string
    {
        return $this->getParentName();
    }

    /**
     * Set current type.
     *
     * @param \Closure|Type $type
     *
     * @return FieldValue
     * @deprecated Do this sort of manipulation in the DocumentAST in the future.
     */
    public function setType($type): FieldValue
    {
        $this->returnType = $type;

        return $this;
    }
}
