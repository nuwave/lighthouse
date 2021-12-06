<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class EventDirective extends BaseDirective implements FieldMiddleware
{
    /**
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    protected $eventsDispatcher;

    public function __construct(EventsDispatcher $eventsDispatcher)
    {
        $this->eventsDispatcher = $eventsDispatcher;
    }

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Dispatch an event after the resolution of a field.

The event constructor will be called with a single argument:
the resolved value of the field.
"""
directive @event(
  """
  Specify the fully qualified class name (FQCN) of the event to dispatch.
  """
  dispatch: String!
) repeatable on FIELD_DEFINITION
GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $eventClassName = $this->namespaceClassName(
            $this->directiveArgValue('dispatch')
        );

        $fieldValue->resultHandler(function ($result) use ($eventClassName) {
            $this->eventsDispatcher->dispatch(
                new $eventClassName($result)
            );

            return $result;
        });

        return $next($fieldValue);
    }
}
