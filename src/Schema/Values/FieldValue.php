<?php

namespace Nuwave\Lighthouse\Schema\Values;

use GraphQL\Deferred;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Type\Definition\ResolveInfo as BaseResolveInfo;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSetFactory;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Execution\Utils\FieldPath;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * @phpstan-type BaseResolver callable(mixed, array<string, mixed>, \Nuwave\Lighthouse\Support\Contracts\GraphQLContext, \GraphQL\Type\Definition\ResolveInfo): mixed
 * @phpstan-type Resolver callable(mixed, array<string, mixed>, \Nuwave\Lighthouse\Support\Contracts\GraphQLContext, \Nuwave\Lighthouse\Execution\ResolveInfo): mixed
 * @phpstan-type ResolverWrapper callable(Resolver): Resolver
 * @phpstan-type ArgumentSetTransformer callable(\Nuwave\Lighthouse\Execution\Arguments\ArgumentSet, \GraphQL\Type\Definition\ResolveInfo): \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet
 */
class FieldValue
{
    /**
     * @var array<string, array{0: array<string, mixed>, 1: \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet}>
     */
    protected static array $transformedResolveArgs = [];

    /**
     * Ordered list of callbacks to transform the incoming argument set.
     *
     * @var array<int, ArgumentSetTransformer>
     */
    protected array $argumentSetTransformers = [];

    /**
     * The actual field resolver.
     *
     * Lazily initialized through setResolver().
     *
     * @var Resolver
     */
    protected $resolver;

    /**
     * @var array<int, ResolverWrapper>
     */
    protected array $resolverWrappers = [];

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
     * Overwrite the current/default resolver.
     *
     * @param  Resolver  $resolver
     *
     * @return $this
     */
    public function setResolver(callable $resolver): self
    {
        $this->resolver = $resolver;

        return $this;
    }

    /**
     * Wrap the previous resolver with another resolver.
     *
     * @param  callable(Resolver): Resolver  $resolverWrapper
     */
    public function wrapResolver(callable $resolverWrapper): void
    {
        $this->resolverWrappers[] = $resolverWrapper;
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
     * @param  Resolver  $handle
     */
    public function resultHandler(callable $handle): void
    {
        $this->wrapResolver(fn (callable $previousResolver) => function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($previousResolver, $handle): mixed {
            $resolved = $previousResolver($root, $args, $context, $resolveInfo);

            if ($resolved instanceof Deferred) {
                return $resolved->then(static function ($result) use ($handle, $args, $context, $resolveInfo) {
                    return $handle($result, $args, $context, $resolveInfo);
                });
            }

            return $handle($resolved, $args, $context, $resolveInfo);
        });
    }

    /**
     * @param  ArgumentSetTransformer  $argumentSetTransformer
     *
     * @return $this
     */
    public function addArgumentSetTransformer(callable $argumentSetTransformer): self
    {
        $this->argumentSetTransformers[] = $argumentSetTransformer;

        return $this;
    }

    /**
     * @return BaseResolver
     */
    public function finishResolver(): callable
    {
        // We expect the wrapped resolvers to run in order, but nesting them causes the last
        // applied wrapper to be run first. Thus, we reverse the wrappers before applying them.
        foreach (array_reverse($this->resolverWrappers) as $wrapper) {
            $this->resolver = $wrapper($this->resolver);
        }

        return function ($root, array $args, GraphQLContext $context, BaseResolveInfo $baseResolveInfo): mixed {
            $path = FieldPath::withoutLists($baseResolveInfo->path);

            if (! isset(self::$transformedResolveArgs[$path])) {
                $argumentSetFactory = app(ArgumentSetFactory::class);
                assert($argumentSetFactory instanceof ArgumentSetFactory);
                $argumentSet = $argumentSetFactory->fromResolveInfo($args, $baseResolveInfo);

                foreach ($this->argumentSetTransformers as $transform) {
                    $argumentSet = $transform($argumentSet, $baseResolveInfo);
                }

                self::$transformedResolveArgs[$path] = [$argumentSet->toArray(), $argumentSet];
            }

            [$args, $argumentSet] = self::$transformedResolveArgs[$path];
            $resolveInfo = new ResolveInfo($baseResolveInfo, $argumentSet);
            $resolveInfo->argumentSet = $argumentSet;

            return ($this->resolver)($root, $args, $context, $resolveInfo);
        };
    }
}
