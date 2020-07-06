<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class EventDirective extends BaseDirective implements FieldMiddleware, DefinedDirective
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
        return /** @lang GraphQL */ <<<'SDL'
"""
Fire an event after a mutation has taken place.
It requires the `dispatch` argument that should be
the class name of the event you want to fire.
"""
directive @event(
  """
  Specify the fully qualified class name (FQCN) of the event to dispatch.
  """
  dispatch: String!
) on FIELD_DEFINITION
SDL;
    }

    /**
     * Resolve the field directive.
     */
    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $eventBaseName = $this->directiveArgValue('dispatch');
        $eventClassName = $this->namespaceClassName($eventBaseName);
        $previousResolver = $fieldValue->getResolver();

        return $next(
            $fieldValue->setResolver(
                function () use ($previousResolver, $eventClassName) {
                    $result = $previousResolver(...func_get_args());

                    $this->eventsDispatcher->dispatch(
                        new $eventClassName($result)
                    );

                    return $result;
                }
            )
        );
    }
}
