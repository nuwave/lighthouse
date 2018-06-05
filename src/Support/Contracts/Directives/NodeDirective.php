<?php


namespace Nuwave\Lighthouse\Support\Contracts\Directives;


use Closure;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Support\Contracts\Directive;

interface NodeDirective extends Directive
{
    public function handleNode(Collection $fields, Closure $next);
}