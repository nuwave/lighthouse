<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Execution\Arguments\ArgPartitioner;
use Nuwave\Lighthouse\Execution\Arguments\TypedArgs;
use Nuwave\Lighthouse\Execution\MutationExecutor;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Execution\Arguments\ResolveNestedAfter;
use Nuwave\Lighthouse\Execution\Arguments\ResolveNestedBefore;
use Closure;

class ArgResolver
{
    /**
     * Deal with nested argument resolvers.
     *
     * @param  \Closure  $resolver
     * @return \Closure
     */
    public function wrapResolver(Closure $resolver): Closure
    {
        return function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver) {
            $argPartitioner = new ArgPartitioner();
            $argPartitioner->setResolverArguments($root, $args, $context, $resolveInfo);
            [$before, $regular, $after] = $argPartitioner->partitionResolverInputs();

            // Prepare a callback that is passed into the field resolver
            // It should be called with the new root object
            $resolveBeforeResolvers = function ($root) use ($before, $context, $resolveInfo) {
                /** @var \Nuwave\Lighthouse\Execution\Arguments\TypedArg $beforeArg */
                foreach ($before as $beforeArg) {
                    /** @var \Nuwave\Lighthouse\Schema\Extensions\ArgumentExtensions $argumentExtensions */
                    $argumentExtensions = $beforeArg->definition['lighthouse'];

                    /** @var ResolveNestedBefore $beforeResolver */
                    foreach ($argumentExtensions->resolveBefore as $beforeResolver) {
                        $beforeResolver->resolveBefore($root, $beforeArg, $context, $resolveInfo);
                    }
                }
            };
            $resolveInfo->resolveBeforeResolvers = $resolveBeforeResolvers;

            $result = $resolver($root, $regular, $context, $resolveInfo);

            /** @var \Nuwave\Lighthouse\Execution\Arguments\TypedArg $afterArg */
            foreach ($after as $afterArg) {
                /** @var \Nuwave\Lighthouse\Schema\Extensions\ArgumentExtensions $argumentExtensions */
                $argumentExtensions = $afterArg->definition['lighthouse'];

                /** @var \Nuwave\Lighthouse\Execution\Arguments\ResolveNestedAfter $afterResolver */
                foreach ($argumentExtensions->resolveAfter as $afterResolver) {
                    $afterResolver->resolveAfter($root, $afterArg, $context, $resolveInfo);
                }
            }

            return $result;
        };
    }
}
