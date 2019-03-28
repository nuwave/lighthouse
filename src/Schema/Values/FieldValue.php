<?php

namespace Nuwave\Lighthouse\Schema\Values;

use Closure;
use GraphQL\Type\Definition\Type;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\Conversion\DefinitionNodeConverter;

class FieldValue
{
    /**
     * An instance of the type that this field returns.
     *
     * @var \GraphQL\Type\Definition\Type|null
     */
    protected $returnType;

    /**
     * The underlying AST definition of the Field.
     *
     * @var \GraphQL\Language\AST\FieldDefinitionNode
     */
    protected $field;

    /**
     * The parent type of the field.
     *
     * @var \Nuwave\Lighthouse\Schema\Values\NodeValue
     */
    protected $parent;

    /**
     * The actual field resolver.
     *
     * @var \Closure|null
     */
    protected $resolver;

    /**
     * Text describing by this field is deprecated.
     *
     * @var string|null
     */
    protected $deprecationReason = null;

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
     * @param  \Nuwave\Lighthouse\Schema\Values\NodeValue  $parent
     * @param  \GraphQL\Language\AST\FieldDefinitionNode  $field
     * @return void
     */
    public function __construct(NodeValue $parent, FieldDefinitionNode $field)
    {
        $this->parent = $parent;
        $this->field = $field;
    }

    /**
     * Overwrite the current/default resolver.
     *
     * @param  \Closure  $resolver
     * @return $this
     */
    public function setResolver(Closure $resolver): self
    {
        $this->resolver = $resolver;

        return $this;
    }

    /**
     * Define a closure that is used to determine the complexity of the field.
     *
     * @param  \Closure  $complexity
     * @return $this
     */
    public function setComplexity(Closure $complexity): self
    {
        $this->complexity = $complexity;

        return $this;
    }

    /**
     * Set deprecation reason for field.
     *
     * @param  string  $deprecationReason
     * @return $this
     */
    public function setDeprecationReason(string $deprecationReason): self
    {
        $this->deprecationReason = $deprecationReason;

        return $this;
    }

    /**
     * Get an instance of the return type of the field.
     *
     * @return \GraphQL\Type\Definition\Type
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
     * @return \Nuwave\Lighthouse\Schema\Values\NodeValue
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
     * @return \GraphQL\Language\AST\FieldDefinitionNode
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
    public function getResolver(): ?Closure
    {
        return $this->resolver;
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
            case 'Subscription':
                return (array) config('lighthouse.namespaces.subscriptions');
            default:
               return [];
        }
    }

    /**
     * @return \GraphQL\Language\AST\StringValueNode|null
     */
    public function getDescription(): ?StringValueNode
    {
        return $this->field->description;
    }

    /**
     * Get current complexity.
     *
     * @return \Closure|null
     */
    public function getComplexity(): ?Closure
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
     * @return string|null
     */
    public function getDeprecationReason(): ?string
    {
        return $this->deprecationReason;
    }

    /**
     * Is the parent of this field one of the root types?
     *
     * @return bool
     */
    public function parentIsRootType(): bool
    {
        return in_array(
            $this->getParentName(),
            ['Query', 'Mutation', 'Subscription']
        );
    }
}
