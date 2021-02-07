<?php

namespace Tests\Integration;

use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Tests\TestCase;
use Tests\Utils\Queries\Foo;

class LifecycleEventsTest extends TestCase
{
    protected $schema = /** @lang GraphQL */ '
    type Query {
        foo: Int
    }
    ';

    public function testDispatchesProperLifecycleEvents(): void
    {
        /** @var \Illuminate\Contracts\Events\Dispatcher $eventsDispatcher */
        $eventsDispatcher = app(EventsDispatcher::class);

        $events = [];

        $eventsDispatcher->listen('*', function ($event) use ($events) {
            $events []= $event;
        });

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo
            }
            ')
            ->assertExactJson([
                'data' => [
                    'foo' => Foo::THE_ANSWER,
                ],
            ]);

        dump($events);
    }
}
