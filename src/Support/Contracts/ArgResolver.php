<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface ArgResolver
{
    /**
     * @param  mixed  $root  the result of the parent resolver
     * @param  mixed|\Nuwave\Lighthouse\Execution\Arguments\ArgumentSet|array<\Nuwave\Lighthouse\Execution\Arguments\ArgumentSet>  $value  the slice of arguments that belongs to this nested resolver
     *
     * @return mixed|null May return the modified $root
     */
    public function __invoke($root, $value);
}
