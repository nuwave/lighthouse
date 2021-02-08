<?php

namespace Nuwave\Lighthouse\Schema\Values;

use Closure;
use GraphQL\Deferred;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\ExecutableTypeNodeConverter;
use Nuwave\Lighthouse\Schema\RootType;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
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
     * Lazily initialized through setResolver().
     *
     * @var callable
     */
    protected $resolver;

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
     * Get an instance of the return type of the field.
     */
    public function getReturnType(): Type
    {
        if ($this->returnType === null) {
            /** @var \Nuwave\Lighthouse\Schema\ExecutableTypeNodeConverter $typeNodeConverter */
            $typeNodeConverter = app(ExecutableTypeNodeConverter::class);
            $this->returnType = $typeNodeConverter->convert($this->field->type);
        }

        return $this->returnType;
    }

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
        return $this->resolver;
    }

    /**
     * Return the namespaces configured for the parent type.
     *
     * @return array<string>
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

    /**
     * Is the parent of this field one of the root types?
     */
    public function parentIsRootType(): bool
    {
        return RootType::isRootType($this->getParentName());
    }

    /**
     * Register a function that will receive the final result and resolver arguments.
     *
     * For example, the following handler tries to double the result.
     * This is somewhat non-sensical, but shows the range of what you can do.
     *
     * function ($result, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) {
     *     if (is_numeric($result)) {
     *         // A common use-case is to transform the result
     *         $result = $result * 2;
     *     }
     *
     *     // You can also filter results conditionally
     *     if ($result === 42) {
     *          return null;
     *     }
     *
     *     // You can also run side-effects
     *     Log::debug("Doubled to {$result}.");
     *
     *     // Don't forget to return something
     *     return $result;
     * }
     *
     * @param callable(mixed $result, array<string, mixed> $args, GraphQLContext $context, ResolveInfo $resolveInfo): mixed $handle
     */
    public function resultHandler(callable $handle): void
    {
        $previousResolver = $this->resolver;

        $this->resolver = function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($previousResolver, $handle) {
            $resolved = $previousResolver($root, $args, $context, $resolveInfo);

            if ($resolved instanceof Deferred) {
                return $resolved->then(static function ($result) use ($handle, $args, $context, $resolveInfo) {
                    return $handle($result, $args, $context, $resolveInfo);
                });
            }

            return $handle($resolved, $args, $context, $resolveInfo);
        };
    }
}
