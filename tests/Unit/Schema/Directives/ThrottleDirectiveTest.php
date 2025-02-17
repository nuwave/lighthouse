<?php declare(strict_types=1);

namespace Tests\Unit\Schema\Directives;

use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Tests\TestCase;
use Tests\Unit\Execution\Fixtures\FooContext;
use Tests\Utils\Queries\Foo;

final class ThrottleDirectiveTest extends TestCase
{
    public function testNamedLimiter(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Int @throttle(name: "test")
        }
        ';

        $queriedKeys = [];

        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->atLeast(1))
            ->method('limiter')
            ->with('test')
            ->willReturn(static fn (): array => [
                Limit::perMinute(1),
                Limit::perMinute(2)->by('another_key'),
                Limit::perMinute(3),
            ]);

        $rateLimiter->expects(self::exactly(3))
            ->method('tooManyAttempts')
            ->willReturnCallback(static function ($key, $maxAttempts) use (&$queriedKeys): bool {
                $queriedKeys[$maxAttempts] = $key;

                return false;
            });

        $rateLimiter->expects(self::exactly(3))
            ->method('hit');

        $this->app->singleton(RateLimiter::class, static fn (): RateLimiter => $rateLimiter);

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);

        $this->assertNotEquals($queriedKeys[1], $queriedKeys[2]);
        $this->assertEquals($queriedKeys[1], $queriedKeys[3]);
    }

    public function testUnlimitedNamedLimiter(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Int @throttle(name: "test")
        }
        ';

        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->atLeast(1))
            ->method('limiter')
            ->with('test')
            ->willReturn(static fn (): Limit => Limit::none());

        $rateLimiter->expects($this->never())
            ->method('tooManyAttempts');

        $rateLimiter->expects($this->never())
            ->method('hit');

        $this->app->singleton(RateLimiter::class, static fn (): RateLimiter => $rateLimiter);

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);
    }

    public function testWithNullRequest(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Int @throttle(name: "test")
        }
        ';

        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->atLeast(1))
            ->method('limiter')
            ->with('test')
            ->willReturn(static fn (): array => [
                Limit::perMinute(1),
            ]);

        $rateLimiter->expects($this->never())
            ->method('hit');

        // create a context with null request
        $this->app->singleton(CreatesContext::class, static fn (): CreatesContext => new class() implements CreatesContext {
            public function generate(?Request $request): GraphQLContext
            {
                return new FooContext();
            }
        });

        $this->app->singleton(RateLimiter::class, static fn (): RateLimiter => $rateLimiter);

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);
    }
}
