<?php


namespace Nuwave\Lighthouse\Support\Contracts\Directives;


use Closure;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Types\Type;

interface NodeDirective extends Directive
{
    public function handleNode(Type $type, Closure $next);
}
