<?php


namespace Nuwave\Lighthouse\Support\Contracts;


use Closure;

interface NodeMiddleware extends Directive
{
    public function handleNode($value, Closure $closure);
}