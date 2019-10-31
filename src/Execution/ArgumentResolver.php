<?php

namespace Nuwave\Lighthouse\Execution;

use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;

interface ArgumentResolver
{
    /**
     * @param  mixed  $root  The result of the parent resolver.
     * @param  mixed|ArgumentSet|ArgumentSet[]  $value  The slice of arguments that belongs to this nested resolver.
     * @return mixed
     */
    public function __invoke($root, $value);
}
