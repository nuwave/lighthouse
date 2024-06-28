<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Carbon\Carbon;
use Faker\Factory;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Response;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Exceptions\RateLimitException;
use Tests\TestCase;
use Tests\Utils\Queries\Foo;

final class ThrottleDirectiveTest extends TestCase
{
    public function testWrongLimiterName(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Int @throttle(name: "test")
        }
        ';

        $this->expectException(DefinitionException::class);
        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ');
    }

    public function testNamedLimiterReturnsRequest(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Int @throttle(name: "test")
        }
        ';

        $rateLimiter = $this->app->make(RateLimiter::class);
        $rateLimiter->for(
            'test',
            static fn (): Response => response('Custom response...', 429),
        );

        $this->expectException(DefinitionException::class);
        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ');
    }

    public function testNamedLimiter(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Int @throttle(name: "test")
        }
        ';

        $query = /** @lang GraphQL */ '
        {
            foo
        }
        ';

        $rateLimiter = $this->app->make(RateLimiter::class);
        $rateLimiter->for(
            'test',
            static fn (): Limit => Limit::perMinute(1),
        );

        $this->graphQL($query)->assertJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);

        $this->graphQL($query)->assertGraphQLError(
            new RateLimitException('Query.foo'),
        );
    }

    public function testLimitClears(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Int @throttle(name: "test")
        }
        ';

        $query = /** @lang GraphQL */ '
        {
            foo
        }
        ';

        $rateLimiter = $this->app->make(RateLimiter::class);
        $rateLimiter->for(
            'test',
            static fn (): Limit => Limit::perMinute(1),
        );

        $knownDate = Carbon::createStrict(2020, 1, 1, 1); // arbitrary known date
        Carbon::setTestNow($knownDate);

        $this->graphQL($query)->assertJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);

        $this->graphQL($query)->assertGraphQLError(
            new RateLimitException('Query.foo'),
        );

        // wait two minutes and assert that the limit is reset
        Carbon::setTestNow($knownDate->copy()->addMinutes(2));

        $this->graphQL($query)->assertJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);

        Carbon::setTestNow();
    }

    public function testInlineLimiter(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Int @throttle(maxAttempts: 1)
        }
        ';

        $query = /** @lang GraphQL */ '
        {
            foo
        }
        ';

        $faker = Factory::create()->unique();
        $ip = $faker->ipv4;
        $ip2 = $faker->ipv4;

        $this->graphQL($query, [], [], ['REMOTE_ADDR' => $ip])->assertJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);

        $this->graphQL($query, [], [], ['REMOTE_ADDR' => $ip])->assertGraphQLError(
            new RateLimitException('Query.foo'),
        );

        $this->graphQL($query, [], [], ['REMOTE_ADDR' => $ip2])->assertJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);
    }
}
