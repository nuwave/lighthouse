<?php


namespace Nuwave\Lighthouse\Support\Contracts\Directives;


use Closure;
use Nuwave\Lighthouse\Support\Contracts\Directive;

interface FieldDirective extends Directive
{
    public function handleField($value, Closure $next);
}