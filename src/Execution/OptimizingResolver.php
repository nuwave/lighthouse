<?php

namespace Nuwave\Lighthouse\Execution;

use GraphQL\Type\Definition\ResolveInfo;
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
     * @var array{0: mixed, 1: array<string, mixed>, 2: GraphQLContext, 3: ResolveInfo}
     */
    protected $transformedResolveArgs;

    public function __construct(callable $oneOffResolver, callable $resolver)
    {
        $this->oneOffResolver = $oneOffResolver;
        $this->resolver = $resolver;
    }

    public function __invoke($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        if (! isset($this->transformedResolveArgs)) {
            $this->transformedResolveArgs = ($this->oneOffResolver)($root, $args, $context, $resolveInfo);
        }

        return ($this->resolver)(...$this->transformedResolveArgs);
    }
}
