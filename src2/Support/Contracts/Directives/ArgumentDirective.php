<?php


namespace Nuwave\Lighthouse\Support\Contracts\Directives;


use Closure;
use Nuwave\Lighthouse\Support\Contracts\Directive;

interface ArgumentDirective extends Directive
{
    public function handleArgument($value, Closure $next);
}