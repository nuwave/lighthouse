<?php

namespace Tests\Integration;

use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Events\BuildExtensionsResponse;
use Nuwave\Lighthouse\Events\BuildSchemaString;
use Nuwave\Lighthouse\Events\EndExecution;
use Nuwave\Lighthouse\Events\EndRequest;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Events\ManipulateResult;
use Nuwave\Lighthouse\Events\StartExecution;
use Nuwave\Lighthouse\Events\StartRequest;
use Tests\TestCase;

class LifecycleEventsTest extends TestCase
{
    public function testDispatchesProperLifecycleEvents(): void
    {
        /** @var \Illuminate\Contracts\Events\Dispatcher $eventsDispatcher */
        $eventsDispatcher = app(EventsDispatcher::class);

        /** @var array<int, object> $events */
        $events = [];

        $eventsDispatcher->listen('*', function (string $name, array $payload) use (&$events) {
            if (Str::startsWith($name, 'Nuwave\\Lighthouse')) {
                // We only fire class-based events, so the payload
                // always holds exactly a single class instance.
                $events []= $payload[0];
            }
        });

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ');

        $this->assertInstanceOf(StartRequest::class, $events[0]);
        $this->assertInstanceOf(BuildSchemaString::class, $events[1]);
        $this->assertInstanceOf(ManipulateAST::class, $events[2]);
        $this->assertInstanceOf(StartExecution::class, $events[3]);
        $this->assertInstanceOf(BuildExtensionsResponse::class, $events[4]);
        $this->assertInstanceOf(ManipulateResult::class, $events[5]);
        $this->assertInstanceOf(EndExecution::class, $events[6]);
        $this->assertInstanceOf(EndRequest::class, $events[7]);
    }
}
