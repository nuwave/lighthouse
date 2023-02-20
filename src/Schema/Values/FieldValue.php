<?php

namespace Nuwave\Lighthouse\Schema\Values;

use GraphQL\Deferred;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSetFactory;
use Nuwave\Lighthouse\Execution\Utils\FieldPath;
use Nuwave\Lighthouse\Schema\ExecutableTypeNodeConverter;
use Nuwave\Lighthouse\Schema\RootType;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * @phpstan-type Resolver callable(mixed, array<string, mixed>, \Nuwave\Lighthouse\Support\Contracts\GraphQLContext, \Nuwave\Lighthouse\Execution\ResolveInfo): mixed
 */
class FieldValue
{
    /**
     * @var array<string, array{0: array<string, mixed>, 1: \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet}>
     */
    protected static $transformedResolveArgs = [];

    /**
     * The actual field resolver.
     *
     * Lazily initialized through setResolver().
     *
     * @var Resolver
     */
    protected $resolver;

    /**
     * Ordered list of callbacks to transform the incoming argument set.
     *
     * @var array<int, callable(\Nuwave\Lighthouse\Execution\Arguments\ArgumentSet, \GraphQL\Type\Definition\ResolveInfo $resolveInfo): \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet>
     */
    protected $argumentSetTransformers;

    public function __construct(
        /**
         * The parent type of the field.
         */
        protected TypeValue $parent,

        /**
         * The underlying AST definition of the Field.
         */
        protected FieldDefinitionNode $field,
    ) {}

    public static function clear(): void
    {
        self::$transformedResolveArgs = [];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return mixed Really anything
     */
    public function __invoke($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $path = FieldPath::withoutLists($resolveInfo->path);

        if (! isset(self::$transformedResolveArgs[$path])) {
            $argumentSetFactory = app(ArgumentSetFactory::class);
            $argumentSet = $argumentSetFactory->fromResolveInfo($args, $resolveInfo);

            foreach ($this->argumentSetTransformers as $transform) {
                $argumentSet = $transform($argumentSet);
            }

            self::$transformedResolveArgs[$path] = [$argumentSet->toArray(), $argumentSet];
        }

        [$args, $argumentSet] = self::$transformedResolveArgs[$path];
        $resolveInfo->argumentSet = $argumentSet;

        return ($this->resolver)($root, $args, $context, $resolveInfo);
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
     * @return $this
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
     * This is somewhat nonsensical, but shows the range of what you can do.
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

    public function addArgumentSetTransformer(callable $argumentSetTransformer): self
    {
        $this->argumentSetTransformers [] = $argumentSetTransformer;

        return $this;
    }
}
