<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use Nuwave\Lighthouse\Execution\ArgumentResolver;

class ArgResolver implements ArgumentResolver
{
    /**
     * @var \Closure|\Nuwave\Lighthouse\Execution\ArgumentResolver
     */
    private $previous;

    /**
     * ArgResolver constructor.
     * @param \Closure|\Nuwave\Lighthouse\Execution\ArgumentResolver $previous
     */
    public function __construct($previous)
    {
        $this->previous = $previous;
    }

    public function __invoke($root, ArgumentSet $args)
    {
        [$nestedArgs, $regularArgs] = ArgPartitioner::nestedArgumentResolvers($args, $root);

        $result = ($this->previous)($root, $regularArgs);

        /** @var \Nuwave\Lighthouse\Execution\Arguments\Argument $nested */
        foreach ($nestedArgs->arguments as $nested) {
            ($nested->resolver)($result, $nested->value);
        }

        return $result;
    }
}
