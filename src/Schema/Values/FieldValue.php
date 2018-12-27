<?php

namespace Nuwave\Lighthouse\Schema\Values;

use GraphQL\Executor\Executor;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Support\Utils;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\AST\FieldDefinitionNode;
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
     * The underlying AST definition of the Field.
     *
     * @var FieldDefinitionNode
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
     * Create new field value instance.
     *
     * @param NodeValue           $parent
     * @param FieldDefinitionNode $field
     */
    public function __construct(NodeValue $parent, FieldDefinitionNode $field)
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
    public function setResolver(\Closure $resolver): self
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
    public function setComplexity(\Closure $complexity): self
    {
        $this->complexity = $complexity;

        return $this;
    }

    /**
     * Get an instance of the return type of the field.
     *
     * @return Type
     */
    public function getReturnType(): Type
    {
        if (! isset($this->returnType)) {
            $this->returnType = app(DefinitionNodeConverter::class)->toType(
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
        return $this->getParent()->getTypeDefinitionName();
    }

    /**
     * Get the underlying AST definition for the field.
     *
     * @return FieldDefinitionNode
     */
    public function getField(): FieldDefinitionNode
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
        if (! isset($this->resolver)) {
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
        if ($this->parentIsRootType()) {
            $resolverClass = Utils::namespaceClassname(
                studly_case($this->getFieldName()),
                $this->defaultNamespacesForParent(),
                function (string $class): bool {
                    return method_exists($class, 'resolve');
                }
            );

            if (! $resolverClass) {
                throw new DefinitionException(
                    "Could not locate a default resolver for the field {$this->field->name->value}"
                );
            }

            return \Closure::fromCallable(
                [app($resolverClass), 'resolve']
            );
        }

        return \Closure::fromCallable(
             [Executor::class, 'defaultFieldResolver']
         );
    }

    /**
     * Return the namespaces configured for the parent type.
     *
     * @return string[]
     */
    public function defaultNamespacesForParent(): array
    {
        switch ($this->getParentName()) {
            case 'Query':
                return (array) config('lighthouse.namespaces.queries');
            case 'Mutation':
                return (array) config('lighthouse.namespaces.mutations');
            default:
               return [];
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
     * Is the parent of this field one of the root types?
     *
     * @return bool
     */
    protected function parentIsRootType(): bool
    {
        return \in_array(
            $this->getParentName(),
            ['Query', 'Mutation']
        );
    }
}
