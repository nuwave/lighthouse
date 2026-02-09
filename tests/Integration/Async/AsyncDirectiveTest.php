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

        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Mutation {
            fooAsync: Boolean! @mock @async
        }
        GRAPHQL;

        $queue = Queue::fake();
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            fooAsync
        }
        GRAPHQL)->assertExactJson([
            'data' => [
                'fooAsync' => true,
            ],
        ]);

        $jobs = $queue->pushed(AsyncMutation::class);
        $this->assertCount(1, $jobs);
        foreach ($jobs as $job) {
            $this->assertInstanceOf(AsyncMutation::class, $job);

            $jobCycledThroughSerialization = unserialize(serialize($job));
            $this->assertInstanceOf(AsyncMutation::class, $jobCycledThroughSerialization);
            Container::getInstance()->call([$jobCycledThroughSerialization, 'handle']);
        }
    }

    public function testDispatchesMutationOnCustomQueue(): void
    {
        $this->mockResolver(static fn (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) => null);

        $this->schema .= /** @lang GraphQL */ <<<'GRAPHQL'
        type Mutation {
            fooAsync: Boolean! @mock @async(queue: "custom")
        }
        GRAPHQL;

        $queue = Queue::fake();
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            fooAsync
        }
        GRAPHQL)->assertExactJson([
            'data' => [
                'fooAsync' => true,
            ],
        ]);

        $jobs = $queue->pushed(AsyncMutation::class);
        $this->assertCount(1, $jobs);
        foreach ($jobs as $job) {
            $this->assertInstanceOf(AsyncMutation::class, $job);
            $this->assertSame('custom', $job->queue);

            $jobCycledThroughSerialization = unserialize(serialize($job));
            $this->assertInstanceOf(AsyncMutation::class, $jobCycledThroughSerialization);
            Container::getInstance()->call([$jobCycledThroughSerialization, 'handle']);
        }
    }

    public function testOnlyOnMutations(): void
    {
        $this->expectExceptionObject(new DefinitionException(
            'The @async directive must only be used on root mutation fields, found it on Query.foo.',
        ));
        $this->buildSchema(/** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo: Boolean! @async
        }
        GRAPHQL);
    }
}
