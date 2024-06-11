<?php declare(strict_types=1);

namespace Tests\Integration\Async;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Queue;
use Nuwave\Lighthouse\Async\AsyncMutation;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Tests\DBTestCase;

final class AsyncDirectiveTest extends DBTestCase
{
    public function testDispatchesMutation(): void
    {
        $this->mockResolver(static fn (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) => null);

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

        $jobs = $queue->pushed(AsyncMutation::class);
        $this->assertCount(1, $jobs);
        foreach ($jobs as $job) {
            assert($job instanceof AsyncMutation);

            $jobCycledThroughSerialization = unserialize(serialize($job));
            assert($jobCycledThroughSerialization instanceof AsyncMutation);
            Container::getInstance()->call([$jobCycledThroughSerialization, 'handle']);
        }
    }

    public function testDispatchesMutationOnCustomQueue(): void
    {
        $this->mockResolver(static fn (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) => null);

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

        $jobs = $queue->pushed(AsyncMutation::class);
        $this->assertCount(1, $jobs);
        foreach ($jobs as $job) {
            assert($job instanceof AsyncMutation);
            $this->assertSame('custom', $job->queue);

            $jobCycledThroughSerialization = unserialize(serialize($job));
            assert($jobCycledThroughSerialization instanceof AsyncMutation);
            Container::getInstance()->call([$jobCycledThroughSerialization, 'handle']);
        }
    }

    public function testOnlyOnMutations(): void
    {
        $this->expectExceptionObject(new DefinitionException(
            'The @async directive must only be used on root mutation fields, found it on Query.foo.',
        ));
        $this->buildSchema(/** @lang GraphQL */ '
        type Query {
            foo: Boolean! @async
        }
        ');
    }
}
