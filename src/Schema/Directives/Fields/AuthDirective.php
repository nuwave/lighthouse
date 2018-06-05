<?php


namespace Nuwave\Lighthouse\Schema\Directives\Fields;


use Closure;
use Nuwave\Lighthouse\Schema\ResolveInfo;
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

        $resolveInfo->result(auth($guard)->user());

        return $next($resolveInfo);
    }
}