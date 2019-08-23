<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Execution\Resolver;
use Nuwave\Lighthouse\Schema\Context;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Execution\Arguments\ArgPartitioner;
use Nuwave\Lighthouse\Execution\Arguments\ResolveNestedBefore;

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
        $argPartitioner->setResolverArguments($root, $args, $context, $resolveInfo);
        [$before, $regular, $after] = $argPartitioner->partitionResolverInputs();

        // Prepare a callback that is passed into the field resolver
        // It should be called with the new root object
        $resolveBeforeResolvers = function ($root) use ($before, $context, $resolveInfo) {
            /** @var \Nuwave\Lighthouse\Execution\Arguments\TypedArg $beforeArg */
            foreach ($before as $beforeArg) {
                // TODO we might continue to automatically wrap the types in ArgResolvers,
                // but we would have to deal with non-null and list types

                ($beforeArg->resolver)($root, $beforeArg->value, $context, $resolveInfo);
            }
        };
        $resolveInfo->resolveBeforeResolvers = $resolveBeforeResolvers;

        $result = ($this->previous)($root, $regular, $context, $resolveInfo);

        /** @var \Nuwave\Lighthouse\Execution\Arguments\TypedArg $afterArg */
        foreach ($after as $afterArg) {
            ($afterArg->resolver)($result, $afterArg->value, $context, $resolveInfo);
        }

        return $result;
    }
}
