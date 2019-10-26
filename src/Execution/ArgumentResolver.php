<?php

namespace Nuwave\Lighthouse\Execution;

use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;

interface ArgumentResolver
{
    /**
     * @param  mixed  $root
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet  $args
     * @return void
     */
    public function __invoke($root, ArgumentSet $args);
}
