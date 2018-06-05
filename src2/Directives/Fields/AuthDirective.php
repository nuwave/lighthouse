<?php


namespace Nuwave\Lighthouse\Directives\Fields;


use Closure;
use Nuwave\Lighthouse\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\Directives\FieldDirective;

class AuthDirective implements FieldDirective
{
    public function name()
    {
        return 'auth';
    }

    public function handleField(ResolveInfo $resolveInfo, Closure $next)
    {
        $arg = $resolveInfo->field()->directive($this->name())->argument('guard');
        $guard = optional($arg)->defaultValue();

        return $next(auth($guard)->user());
    }
}