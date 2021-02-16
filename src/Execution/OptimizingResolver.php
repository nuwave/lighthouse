<?php

namespace Nuwave\Lighthouse\Execution;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Execution\DataLoader\BatchLoaderRegistry;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class OptimizingResolver
{
    /**
     * @var callable
     */
    protected $oneOffResolver;

    /**
     * @var callable
     */
    protected $resolver;

    /**
     * @var array<string, array{0: array<string, mixed>, 1: ArgumentSet}>
     */
    protected $transformedResolveArgs;

    public function __construct(callable $oneOffResolver, callable $resolver)
    {
        $this->oneOffResolver = $oneOffResolver;
        $this->resolver = $resolver;
    }

    /**
     * @param  array<string, mixed>  $args
     * @return mixed Really anything
     */
    public function __invoke($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $instanceKey = BatchLoaderRegistry::instanceKey($resolveInfo->path);

        if (! isset($this->transformedResolveArgs[$instanceKey])) {
            $this->transformedResolveArgs[$instanceKey] = ($this->oneOffResolver)($root, $args, $context, $resolveInfo);
        }

        [$args, $argumentSet] = $this->transformedResolveArgs[$instanceKey];
        $resolveInfo->argumentSet = $argumentSet;

        return ($this->resolver)($root, $args, $context, $resolveInfo);
    }
}
