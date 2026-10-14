<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class EventDirective extends BaseDirective implements FieldMiddleware
{
    public function __construct(
        protected EventsDispatcher $eventsDispatcher,
    ) {}

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

    public function handleField(FieldValue $fieldValue): void
    {
        $eventClassName = $this->namespaceClassName(
            $this->directiveArgValue('dispatch'),
        );

        $fieldValue->resultHandler(function ($result) use ($eventClassName) {
            $this->eventsDispatcher->dispatch(
                new $eventClassName($result),
            );

            return $result;
        });
    }
}
