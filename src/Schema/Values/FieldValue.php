<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Values;

use GraphQL\Deferred;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Type\Definition\ResolveInfo as BaseResolveInfo;
use Illuminate\Container\Container;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSetFactory;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Execution\Utils\FieldPath;
use Nuwave\Lighthouse\Schema\RootType;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * @phpstan-import-type FieldResolver from \GraphQL\Executor\Executor as FieldResolverFn
 *
 * @phpstan-type Resolver callable(mixed, array<string, mixed>, \Nuwave\Lighthouse\Support\Contracts\GraphQLContext, \Nuwave\Lighthouse\Execution\ResolveInfo): mixed
 * @phpstan-type ResolverWrapper callable(Resolver): Resolver
 * @phpstan-type ArgumentSetTransformer callable(\Nuwave\Lighthouse\Execution\Arguments\ArgumentSet, \GraphQL\Type\Definition\ResolveInfo): \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet
 */
class FieldValue
{
    /** @var array<string, array{0: array<string, mixed>, 1: \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet}> */
    protected static array $transformedResolveArgs = [];

    /**
     * Ordered list of callbacks to transform the incoming argument set.
     *
     * @var array<int, ArgumentSetTransformer>
     */
    protected array $argumentSetTransformers = [];

    /** @var array<int, ResolverWrapper> */
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

    /** Get the underlying AST definition for the field. */
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

    /** @return array<int, string> */
    public function parentNamespaces(): array
    {
        $parentName = $this->getParentName();

        return match ($parentName) {
            RootType::QUERY => (array) config('lighthouse.namespaces.queries'),
            RootType::MUTATION => (array) config('lighthouse.namespaces.mutations'),
            RootType::SUBSCRIPTION => (array) config('lighthouse.namespaces.subscriptions'),
            default => array_map(
                static fn (string $typesNamespace): string => "{$typesNamespace}\\{$parentName}",
                (array) config('lighthouse.namespaces.types'),
            ),
        };
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
        $this->wrapResolver(static fn (callable $resolver): \Closure => static function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver, $handle): mixed {
            $resolved = $resolver($root, $args, $context, $resolveInfo);
            if ($resolved instanceof Deferred) {
                return $resolved->then(static fn (mixed $result): mixed => $handle($result, $args, $context, $resolveInfo));
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
     * Apply wrappers and transformation to the innermost resolver function.
     *
     * @param  Resolver  $resolver
     *
     * @return FieldResolverFn
     */
    public function finishResolver(callable $resolver): callable
    {
        // We expect the wrapped resolvers to run in order, but nesting them causes the last
        // applied wrapper to be run first. Thus, we reverse the wrappers before applying them.
        foreach (array_reverse($this->resolverWrappers) as $wrapper) {
            $resolver = $wrapper($resolver);
        }

        return function (mixed $root, array $baseArgs, GraphQLContext $context, BaseResolveInfo $baseResolveInfo) use ($resolver): mixed {
            $path = FieldPath::withoutLists($baseResolveInfo->path);

            if (! isset(self::$transformedResolveArgs[$path])) {
                $argumentSetFactory = Container::getInstance()->make(ArgumentSetFactory::class);
                assert($argumentSetFactory instanceof ArgumentSetFactory);

                $argumentSet = $argumentSetFactory->fromResolveInfo($baseArgs, $baseResolveInfo);
                foreach ($this->argumentSetTransformers as $transform) {
                    $argumentSet = $transform($argumentSet, $baseResolveInfo);
                }

                $args = $argumentSet->toArray();

                self::$transformedResolveArgs[$path] = [$args, $argumentSet];
            } else {
                [$args, $argumentSet] = self::$transformedResolveArgs[$path];
            }

            return ($resolver)($root, $args, $context, new ResolveInfo($baseResolveInfo, $argumentSet));
        };
    }
}
