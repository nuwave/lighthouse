<?php declare(strict_types=1);

namespace Tests\Unit\Schema\Directives;

use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Tests\TestCase;
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
        $rateLimiter->expects(self::atLeast(1))
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
        $rateLimiter->expects(self::atLeast(1))
            ->method('limiter')
            ->with('test')
            ->willReturn(static fn (): Limit => Limit::none());

        $rateLimiter->expects(self::never())
            ->method('tooManyAttempts');

        $rateLimiter->expects(self::never())
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
}
