<?php

namespace Tests\Unit\Schema\Directives;

use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Tests\TestCase;
use Tests\Utils\Queries\Foo;

class ThrottleDirectiveTest extends TestCase
{
    public function testNamedLimiter(): void
    {
        /** @var RateLimiter $rateLimiter */
        $rateLimiter = $this->app->make(RateLimiter::class);
        if (! method_exists($rateLimiter, 'limiter') || ! class_exists('Illuminate\Cache\RateLimiting\Limit')) {
            return;
        }

        $this->schema = /** @lang GraphQL */'
        type Query {
            foo: Int @throttle(name: "test")
        }
        ';

        $queriedKeys = [];
        $this->app->singleton(RateLimiter::class, function () use (&$queriedKeys) {
            $rateLimiter = $this->createMock(RateLimiter::class);
            /** @phpstan-ignore-next-line error for old versions of Laravel */
            $rateLimiter->expects(self::once())
                ->method('limiter')
                ->with('test')
                ->willReturn(function () {
                    return [
                        Limit::perMinute(1),
                        Limit::perMinute(2)->by('another_key'),
                        Limit::perMinute(3),
                    ];
                });

            $rateLimiter->expects(self::exactly(3))
                ->method('tooManyAttempts')
                ->willReturnCallback(function ($key, $maxAttempts) use (&$queriedKeys) {
                    $queriedKeys[$maxAttempts] = $key;

                    return false;
                });

            $rateLimiter->expects(self::exactly(3))
                ->method('hit');

            return $rateLimiter;
        });

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertJson(
            [
                'data' => [
                    'foo' => Foo::THE_ANSWER,
                ],
            ]
        );

        $this->assertNotEquals($queriedKeys[1], $queriedKeys[2]);
        $this->assertEquals($queriedKeys[1], $queriedKeys[3]);
    }

    public function testUnlimitedNamedLimiter(): void
    {
        /** @var RateLimiter $rateLimiter */
        $rateLimiter = $this->app->make(RateLimiter::class);
        if (! method_exists($rateLimiter, 'limiter') || ! class_exists('Illuminate\Cache\RateLimiting\Limit')) {
            return;
        }

        $this->schema = /** @lang GraphQL */'
        type Query {
            foo: Int @throttle(name: "test")
        }
        ';

        $this->app->singleton(RateLimiter::class, function () {
            $rateLimiter = $this->createMock(RateLimiter::class);
            /** @phpstan-ignore-next-line error for old versions of Laravel */
            $rateLimiter->expects(self::once())
                ->method('limiter')
                ->with('test')
                ->willReturn(function () {
                    return Limit::none();
                });

            $rateLimiter->expects(self::never())
                ->method('tooManyAttempts');

            $rateLimiter->expects(self::never())
                ->method('hit');

            return $rateLimiter;
        });

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertJson(
            [
                'data' => [
                    'foo' => Foo::THE_ANSWER,
                ],
            ]
        );
    }
}
