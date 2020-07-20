<?php

namespace Nuwave\Lighthouse\Schema\Values;

use Closure;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\ExecutableTypeNodeConverter;
use Nuwave\Lighthouse\Schema\RootType;
use Nuwave\Lighthouse\Support\Contracts\ProvidesResolver;
use Nuwave\Lighthouse\Support\Contracts\ProvidesSubscriptionResolver;

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
     * @var \Nuwave\Lighthouse\Schema\Values\TypeValue
     */
    protected $parent;

    /**
     * The actual field resolver.
     *
     * @var callable|null
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
     * @var \Closure|null
     */
    protected $complexity;

    public function __construct(TypeValue $parent, FieldDefinitionNode $field)
    {
        $this->parent = $parent;
        $this->field = $field;
    }

    /**
     * Overwrite the current/default resolver.
     *
     * @return $this
     */
    public function setResolver(callable $resolver): self
    {
        $this->resolver = $resolver;

        return $this;
    }

    /**
     * Use the default resolver.
     *
     * @return $this
     */
    public function useDefaultResolver(): self
    {
        $this->resolver = $this->getParentName() === RootType::SUBSCRIPTION
            ? app(ProvidesSubscriptionResolver::class)->provideSubscriptionResolver($this)
            : app(ProvidesResolver::class)->provideResolver($this);

        return $this;
    }

    /**
     * Define a closure that is used to determine the complexity of the field.
     *
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
     * @return $this
     */
    public function setDeprecationReason(string $deprecationReason): self
    {
        $this->deprecationReason = $deprecationReason;

        return $this;
    }

    /**
     * Get an instance of the return type of the field.
     */
    public function getReturnType(): Type
    {
        if (! isset($this->returnType)) {
            /** @var \Nuwave\Lighthouse\Schema\ExecutableTypeNodeConverter $typeNodeConverter */
            $typeNodeConverter = app(ExecutableTypeNodeConverter::class);
            $this->returnType = $typeNodeConverter->convert($this->field->type);
        }

        return $this->returnType;
    }

    /**
     * @return \Nuwave\Lighthouse\Schema\Values\TypeValue
     */
    public function getParent(): TypeValue
    {
        return $this->parent;
    }

    public function getParentName(): string
    {
        return $this->getParent()->getTypeDefinitionName();
    }

    /**
     * Get the underlying AST definition for the field.
     */
    public function getField(): FieldDefinitionNode
    {
        return $this->field;
    }

    /**
     * Get field resolver.
     */
    public function getResolver(): callable
    {
        return $this->resolver; // @phpstan-ignore-line This must only be called after setResolver() was called
    }

    /**
     * Return the namespaces configured for the parent type.
     *
     * @return string[]
     */
    public function defaultNamespacesForParent(): array
    {
        switch ($this->getParentName()) {
            case RootType::QUERY:
                return (array) config('lighthouse.namespaces.queries');
            case RootType::MUTATION:
                return (array) config('lighthouse.namespaces.mutations');
            case RootType::SUBSCRIPTION:
                return (array) config('lighthouse.namespaces.subscriptions');
            default:
               return [];
        }
    }

    public function getDescription(): ?StringValueNode
    {
        return $this->field->description;
    }

    /**
     * Get current complexity.
     */
    public function getComplexity(): ?Closure
    {
        return $this->complexity;
    }

    public function getFieldName(): string
    {
        return $this->field->name->value;
    }

    public function getDeprecationReason(): ?string
    {
        return $this->deprecationReason;
    }

    /**
     * Is the parent of this field one of the root types?
     */
    public function parentIsRootType(): bool
    {
        return RootType::isRootType($this->getParentName());
    }
}
