<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Closure;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;

class EventDirective extends BaseDirective implements FieldMiddleware
{
    /**
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    protected $eventsDispatcher;

    /**
     * Construct EventDirective.
     *
     * @param  \Illuminate\Contracts\Events\Dispatcher  $eventsDispatcher
     * @return void
     */
    public function __construct(EventsDispatcher $eventsDispatcher)
    {
        $this->eventsDispatcher = $eventsDispatcher;
    }

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'event';
    }

    /**
     * Resolve the field directive.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $value
     * @param  \Closure  $next
     *
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    public function handleField(FieldValue $value, Closure $next): FieldValue
    {
        $eventBaseName = $this->directiveArgValue('dispatch')
            /*
             * @deprecated The aliases for dispatch will be removed in v4
             */
            ?? $this->directiveArgValue('fire')
            ?? $this->directiveArgValue('class');
        $eventClassName = $this->namespaceClassName($eventBaseName);
        $previousResolver = $value->getResolver();

        return $next(
            $value->setResolver(
                function () use ($previousResolver, $eventClassName) {
                    $result = call_user_func_array($previousResolver, func_get_args());

                    $this->eventsDispatcher->dispatch(
                        new $eventClassName($result)
                    );

                    return $result;
                }
            )
        );
    }
}
