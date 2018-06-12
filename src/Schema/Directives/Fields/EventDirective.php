<?php


namespace Nuwave\Lighthouse\Schema\Directives\Fields;


use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Nuwave\Lighthouse\Schema\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\Directives\FieldDirective;

class EventDirective implements FieldDirective
{
    protected $eventDispatcher;

    /**
     * EventDirective constructor.
     *
     * @param $eventDispatcher
     */
    public function __construct(Dispatcher $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }


    public function name()
    {
       return 'event';
    }

    public function handleField(ResolveInfo $resolveInfo, Closure $next)
    {
        $event = optional($resolveInfo->field()->directive($this->name())->argument('fire'))->defaultValue();

        $resolveInfo->addAfter(function ($result) use ($event){
            $this->eventDispatcher->dispatch(new $event($result));
        });

        return $next($resolveInfo);
    }
}
