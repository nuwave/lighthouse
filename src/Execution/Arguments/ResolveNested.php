<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use Nuwave\Lighthouse\Execution\ArgumentResolver;

class ResolveNested implements ArgumentResolver
{
    /**
     * @var callable|\Nuwave\Lighthouse\Execution\ArgumentResolver|null
     */
    protected $previous;

    /**
     * ArgResolver constructor.
     *
     * @param  callable|\Nuwave\Lighthouse\Execution\ArgumentResolver|null  $previous
     * @return void
     */
    public function __construct(callable $previous = null)
    {
        $this->previous = $previous;
    }
    /**
     * @param  mixed  $root
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet  $args
     * @return mixed
     */
    public function __invoke($root, $args)
    {
        [$nestedArgs, $regularArgs] = ArgPartitioner::nestedArgumentResolvers($args, $root);

        if($this->previous) {
            $root = ($this->previous)($root, $regularArgs);
        }

        /** @var \Nuwave\Lighthouse\Execution\Arguments\Argument $nested */
        foreach ($nestedArgs->arguments as $nested) {
            ($nested->resolver)($root, $nested->value);
        }

        return $root;
    }
}
