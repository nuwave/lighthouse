<?php declare(strict_types=1);

namespace Tests\Integration\Async;

use Illuminate\Queue\CallQueuedClosure;
use Illuminate\Support\Facades\Queue;
use Tests\DBTestCase;

final class AsyncDirectiveTest extends DBTestCase
{
    public function testDispatchesMutation(): void
    {
        $this->mockResolver('bar');

        $this->schema .= /** @lang GraphQL */ '
        type Mutation {
            fooAsync: Boolean! @mock @async
        }
        ';

        $queue = Queue::fake();
        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            fooAsync
        }
        ')->assertExactJson([
            'data' => [
                'fooAsync' => true,
            ],
        ]);

        $jobs = $queue->pushed(CallQueuedClosure::class);
        $this->assertCount(1, $jobs);
        foreach ($jobs as $job) {
            assert($job instanceof CallQueuedClosure);
            $job->handle($this->app);
        }
    }

    public function testDispatchesMutationOnCustomQueue(): void
    {
        $this->mockResolver('bar');

        $this->schema .= /** @lang GraphQL */ '
        type Mutation {
            fooAsync: Boolean! @mock @async(queue: "custom")
        }
        ';

        $queue = Queue::fake();
        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            fooAsync
        }
        ')->assertExactJson([
            'data' => [
                'fooAsync' => true,
            ],
        ]);

        $jobs = $queue->pushed(CallQueuedClosure::class);
        $this->assertCount(1, $jobs);
        foreach ($jobs as $job) {
            assert($job instanceof CallQueuedClosure);
            $this->assertSame('custom', $job->queue);
            $job->handle($this->app);
        }
    }
}
