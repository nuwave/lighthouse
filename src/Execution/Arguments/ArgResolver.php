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
        $argPartitioner = new ArgPartitioner();
        [$regular, $nestedArgs] = $argPartitioner->partitionResolverInputs($root, $args);

        $result = ($this->previous)($root, $regular);

        /** @var \Nuwave\Lighthouse\Execution\Arguments\Argument $nested */
        foreach ($nestedArgs->arguments as $nested) {
            ($nested->resolver)($result, $nested->value);
        }

        return $result;
    }
}
