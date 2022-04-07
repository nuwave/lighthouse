<?php

namespace Nuwave\Lighthouse\Schema\Values;

use Closure;
use GraphQL\Deferred;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\ExecutableTypeNodeConverter;
use Nuwave\Lighthouse\Schema\Factories\FieldFactory;
use Nuwave\Lighthouse\Schema\RootType;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * @phpstan-type Resolver callable(mixed, array<string, mixed>, GraphQLContext, ResolveInfo): mixed
 */
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
     * @var Resolver
     */
    protected $resolver;

    /**
     * A closure that determines the complexity of executing the field.
     *
     * @deprecated will be removed in v6
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
     * Get the underlying AST definition for the field.
     */
    public function getField(): FieldDefinitionNode
    {
        return $this->field;
    }

    public function getFieldName(): string
    {
        return $this->field->name->value;
    }

    public function getParent(): TypeValue
    {
        return $this->parent;
    }

    public function getParentName(): string
    {
        return $this->parent->getTypeDefinitionName();
    }

    /**
     * Get field resolver.
     *
     * @return Resolver
     */
    public function getResolver(): callable
    {
        return $this->resolver;
    }

    /**
     * Overwrite the current/default resolver.
     *
     * @param  Resolver  $resolver
     */
    public function setResolver(callable $resolver): self
    {
        $this->resolver = $resolver;

        return $this;
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
     *     // You can also run side effects
     *     Log::debug("Doubled to {$result}.");
     *
     *     // Don't forget to return something
     *     return $result;
     * }
     *
     * @param Resolver $handle
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

    /**
     * Return the namespaces configured for the parent type.
     *
     * @deprecated will be removed in v6
     *
     * @return array<string>
     */
    public function defaultNamespacesForParent(): array
    {
        return RootType::defaultNamespaces($this->getParentName());
    }

    /**
     * @deprecated will be removed in v6
     */
    public function getDescription(): ?StringValueNode
    {
        return $this->field->description;
    }

    /**
     * Is the parent of this field one of the root types?
     *
     * @deprecated will be removed in v6
     */
    public function parentIsRootType(): bool
    {
        return RootType::isRootType($this->getParentName());
    }

    /**
     * Use the default resolver.
     *
     * @deprecated will be removed in v6
     */
    public function useDefaultResolver(): self
    {
        $this->resolver = FieldFactory::defaultResolver($this);

        return $this;
    }

    /**
     * Get current complexity.
     *
     * @deprecated will be removed in v6
     */
    public function getComplexity(): ?Closure
    {
        return $this->complexity;
    }

    /**
     * Define a closure that is used to determine the complexity of the field.
     *
     * @deprecated will be removed in v6
     */
    public function setComplexity(Closure $complexity): self
    {
        $this->complexity = $complexity;

        return $this;
    }

    /**
     * Get an instance of the return type of the field.
     *
     * @deprecated will be removed in v6
     */
    public function getReturnType(): Type
    {
        if (null === $this->returnType) {
            /** @var \Nuwave\Lighthouse\Schema\ExecutableTypeNodeConverter $typeNodeConverter */
            $typeNodeConverter = app(ExecutableTypeNodeConverter::class);
            $this->returnType = $typeNodeConverter->convert($this->field->type);
        }

        return $this->returnType;
    }
}
