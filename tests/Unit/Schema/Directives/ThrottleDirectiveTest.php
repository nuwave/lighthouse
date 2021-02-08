<?php

namespace Tests\Unit\Schema\Directives;

use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Nuwave\Lighthouse\Support\AppVersion;
use Tests\TestCase;
use Tests\Utils\Queries\Foo;

class ThrottleDirectiveTest extends TestCase
{
    public function testNamedLimiter(): void
    {
        if (AppVersion::below(8.0)) {
            $this->markTestSkipped('Version less than 8.0 does not support named requests.');
        }

        $this->schema = /** @lang GraphQL */'
        type Query {
            foo: Int @throttle(name: "test")
        }
        ';

        $queriedKeys = [];

        $rateLimiter = $this->createMock(RateLimiter::class);
        // @phpstan-ignore-next-line phpstan ignores markTestSkipped
        $rateLimiter->expects(self::atLeast(1))
            ->method('limiter')
            ->with('test')
            ->willReturn(static function (): array {
                return [
                    // @phpstan-ignore-next-line phpstan ignores markTestSkipped
                    Limit::perMinute(1),
                    // @phpstan-ignore-next-line phpstan ignores markTestSkipped
                    Limit::perMinute(2)->by('another_key'),
                    /// @phpstan-ignore-next-line phpstan ignores markTestSkipped
                    Limit::perMinute(3),
                ];
            });

        $rateLimiter->expects(self::exactly(3))
            ->method('tooManyAttempts')
            ->willReturnCallback(static function ($key, $maxAttempts) use (&$queriedKeys): bool {
                $queriedKeys[$maxAttempts] = $key;

                return false;
            });

        $rateLimiter->expects(self::exactly(3))
            ->method('hit');

        $this->app->singleton(RateLimiter::class, static function () use ($rateLimiter): RateLimiter {
            return $rateLimiter;
        });

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
        if (AppVersion::below(8.0)) {
            $this->markTestSkipped('Version less than 8.0 does not support named requests.');
        }

        $this->schema = /** @lang GraphQL */'
        type Query {
            foo: Int @throttle(name: "test")
        }
        ';

        $rateLimiter = $this->createMock(RateLimiter::class);
        // @phpstan-ignore-next-line phpstan ignores markTestSkipped
        $rateLimiter->expects(self::atLeast(1))
            ->method('limiter')
            ->with('test')
            ->willReturn(static function () {
                // @phpstan-ignore-next-line phpstan ignores markTestSkipped
                return Limit::none();
            });

        $rateLimiter->expects(self::never())
            ->method('tooManyAttempts');

        $rateLimiter->expects(self::never())
            ->method('hit');

        $this->app->singleton(RateLimiter::class, static function () use ($rateLimiter): RateLimiter {
            return $rateLimiter;
        });

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
