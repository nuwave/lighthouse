<?php

namespace Nuwave\Lighthouse\Execution;

use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;

interface ArgumentResolver
{
    /**
     * @param  mixed  $root
     * @param  mixed|ArgumentSet|ArgumentSet[]  $value
     * @return mixed
     */
    public function __invoke($root, $value);
}
