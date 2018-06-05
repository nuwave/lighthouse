<?php


namespace Nuwave\Lighthouse\Directives\Fields;


use Closure;
use Nuwave\Lighthouse\Support\Contracts\Directives\FieldDirective;

class AuthDirective implements FieldDirective
{

    public function name()
    {
        return 'auth';
    }

    public function handleField($value, Closure $next)
    {
        return $next(auth()->user());
    }
}