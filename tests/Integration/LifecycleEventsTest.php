<?php declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
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

final class LifecycleEventsTest extends TestCase
{
    public function testDispatchesProperLifecycleEvents(): void
    {
        $eventsDispatcher = $this->app->make(EventsDispatcher::class);

        /** @var array<int, object> $events */
        $events = [];

        $eventsDispatcher->listen('*', static function (string $name, array $payload) use (&$events): void {
            if (str_starts_with($name, 'Nuwave\\Lighthouse')) {
                // We only fire class-based events, so the payload
                // always holds exactly a single class instance.
                $events[] = $payload[0];
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

        [
            $startRequest,
            $startOperationOrOperations,
            $buildSchemaString,
            $registerDirectiveNamespaces,
            $manipulateAST,
            $startExecution,
            $buildExtensionsResponse,
            $manipulateResult,
            $endExecution,
            $endOperationOrOperations,
            $endRequest,
        ] = $events;

        $this->assertInstanceOf(StartRequest::class, $startRequest);
        $this->assertInstanceOf(StartOperationOrOperations::class, $startOperationOrOperations);
        $this->assertInstanceOf(BuildSchemaString::class, $buildSchemaString);
        $this->assertInstanceOf(RegisterDirectiveNamespaces::class, $registerDirectiveNamespaces);
        $this->assertInstanceOf(ManipulateAST::class, $manipulateAST);
        $this->assertInstanceOf(StartExecution::class, $startExecution);
        $this->assertInstanceOf(BuildExtensionsResponse::class, $buildExtensionsResponse);
        $this->assertInstanceOf(ManipulateResult::class, $manipulateResult);
        $this->assertInstanceOf(EndExecution::class, $endExecution);
        $this->assertInstanceOf(EndOperationOrOperations::class, $endOperationOrOperations);
        $this->assertInstanceOf(EndRequest::class, $endRequest);
    }
}
