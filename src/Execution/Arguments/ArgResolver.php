<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Execution\Resolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class ArgResolver implements Resolver
{
    /**
     * @var \Closure|\Nuwave\Lighthouse\Execution\Resolver
     */
    private $previous;

    /**
     * ArgResolver constructor.
     * @param \Closure|\Nuwave\Lighthouse\Execution\Resolver $previous
     */
    public function __construct($previous)
    {
        $this->previous = $previous;
    }

    public function __invoke($root, $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $argPartitioner = new ArgPartitioner();
        [$before, $regular, $after] = $argPartitioner->partitionResolverInputs($root, $resolveInfo->argumentSet);

        // Prepare a callback that is passed into the field resolver
        // It should be called with the new root object
        $resolveBeforeResolvers = function ($root) use ($before, $context, $resolveInfo) {
            /** @var \Nuwave\Lighthouse\Execution\Arguments\Argument $beforeArg */
            foreach ($before as $beforeArg) {
                ($beforeArg->resolver)($root, $beforeArg->value, $context, $resolveInfo);
            }
        };
        $resolveInfo->resolveBeforeResolvers = $resolveBeforeResolvers;

        $result = ($this->previous)($root, $regular, $context, $resolveInfo);

        /** @var \Nuwave\Lighthouse\Execution\Arguments\Argument $afterArg */
        foreach ($after as $afterArg) {
            ($afterArg->resolver)($result, $afterArg->value, $context, $resolveInfo);
        }

        return $result;
    }
}
