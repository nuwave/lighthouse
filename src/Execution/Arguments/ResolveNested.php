<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use Nuwave\Lighthouse\Support\Contracts\ArgResolver;

class ResolveNested implements ArgResolver
{
    /**
     * @var callable|\Nuwave\Lighthouse\Support\Contracts\ArgResolver|null
     */
    protected $previous;

    /**
     * @var callable
     */
    protected $argPartitioner;

    /**
     * @param  callable|\Nuwave\Lighthouse\Support\Contracts\ArgResolver|null  $previous
     */
    public function __construct(callable $previous = null, callable $argPartitioner = null)
    {
        $this->previous = $previous;
        $this->argPartitioner = $argPartitioner ?? [ArgPartitioner::class, 'nestedArgResolvers'];
    }

    /**
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet  $args
     */
    public function __invoke($root, $args)
    {
        [$nestedArgs, $regularArgs] = ($this->argPartitioner)($args, $root);

        if ($this->previous) {
            $root = ($this->previous)($root, $regularArgs);
        }

        /** @var \Nuwave\Lighthouse\Execution\Arguments\Argument $nested */
        foreach ($nestedArgs->arguments as $nested) {
            // @phpstan-ignore-next-line we know the resolver is there because we partitioned for it
            ($nested->resolver)($root, $nested->value);
        }

        return $root;
    }
}
