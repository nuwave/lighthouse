<?php

namespace Tests\Integration;

use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Events\BuildExtensionsResponse;
use Nuwave\Lighthouse\Events\BuildSchemaString;
use Nuwave\Lighthouse\Events\EndExecution;
use Nuwave\Lighthouse\Events\EndOperationOrOperations;
use Nuwave\Lighthouse\Events\EndRequest;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Events\ManipulateResult;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;
use Nuwave\Lighthouse\Events\StartExecution;
use Nuwave\Lighthouse\Events\StartOperationOrOperations;
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
                $events [] = $payload[0];
            }
        });

        $this->mockResolver();

        $this->schema = /** @lang GraphQL */ '
        type Query {
           foo: Int @mock
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ');

        $this->assertInstanceOf(StartRequest::class, array_shift($events));
        $this->assertInstanceOf(StartOperationOrOperations::class, array_shift($events));
        $this->assertInstanceOf(BuildSchemaString::class, array_shift($events));
        $this->assertInstanceOf(RegisterDirectiveNamespaces::class, array_shift($events));
        $this->assertInstanceOf(ManipulateAST::class, array_shift($events));
        $this->assertInstanceOf(StartExecution::class, array_shift($events));
        $this->assertInstanceOf(BuildExtensionsResponse::class, array_shift($events));
        $this->assertInstanceOf(ManipulateResult::class, array_shift($events));
        $this->assertInstanceOf(EndExecution::class, array_shift($events));
        $this->assertInstanceOf(EndOperationOrOperations::class, array_shift($events));
        $this->assertInstanceOf(EndRequest::class, array_shift($events));
    }
}
