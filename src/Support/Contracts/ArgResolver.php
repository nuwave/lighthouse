<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;

interface ArgResolver
{
    /**
     * @param  mixed  $root  The result of the parent resolver.
     * @param  mixed|ArgumentSet|ArgumentSet[]  $value  The slice of arguments that belongs to this nested resolver.
     * @return mixed
     */
    public function __invoke($root, $value);
}
