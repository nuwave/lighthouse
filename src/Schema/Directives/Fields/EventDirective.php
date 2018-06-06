<?php


namespace Nuwave\Lighthouse\Schema\Directives\Fields;


use Closure;
use Nuwave\Lighthouse\Schema\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\Directives\FieldDirective;

class EventDirective implements FieldDirective
{

    public function name()
    {
       return 'event';
    }

    public function handleField(ResolveInfo $resolveInfo, Closure $next)
    {
        $event = optional($resolveInfo->field()->directive($this->name())->argument('fire'))->defaultValue();

        $resolveInfo->addAfter(function ($result) use ($event){
            event(new $event($result));
        });

        return $next($resolveInfo);
    }
}
