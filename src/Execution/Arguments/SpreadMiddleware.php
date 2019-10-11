<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class SpreadMiddleware
{
    /**
     * @var \Nuwave\Lighthouse\Execution\Arguments\TypedArgs
     */
    protected $typedArgs;

    /**
     * SpreadMiddleware constructor.
     *
     * @param  \Nuwave\Lighthouse\Execution\Arguments\TypedArgs  $typedArgs
     * @return void
     */
    public function __construct(TypedArgs $typedArgs)
    {
        $this->typedArgs = $typedArgs;
    }

    /**
     * Apply the @spread directive and pass on the modified args.
     *
     * @param  \Closure  $next
     * @return \Closure
     */
    public function wrap(\Closure $next): \Closure
    {
        return function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($next) {
            return $next(
                $root,
                $this->typedArgs
                    ->fromResolveInfo($args, $resolveInfo)
                    ->spread()
                    ->toArray(),
                $context,
                $resolveInfo
            );
        };
    }
}
