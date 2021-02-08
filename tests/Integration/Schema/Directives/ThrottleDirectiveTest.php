<?php

namespace Tests\Integration\Schema\Directives;

use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Response;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Exceptions\RateLimitException;
use Nuwave\Lighthouse\Support\AppVersion;
use Tests\TestCase;
use Tests\Utils\Queries\Foo;

class ThrottleDirectiveTest extends TestCase
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
        if (AppVersion::below(8.0)) {
            $this->markTestSkipped('Version less than 8.0 does not support named requests.');
        }

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Int @throttle(name: "test")
        }
        ';

        /** @var \Illuminate\Cache\RateLimiter $rateLimiter */
        $rateLimiter = $this->app->make(RateLimiter::class);
        $this->assertTrue(method_exists($rateLimiter, 'for'));
        $rateLimiter->for(
            'test',
            static function (): Response {
                return response('Custom response...', 429);
            }
        );

        $this->expectException(DirectiveException::class);
        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ');
    }

    public function testNamedLimiter(): void
    {
        if (AppVersion::below(8.0)) {
            $this->markTestSkipped('Version less than 8.0 does not support named requests.');
        }

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Int @throttle(name: "test")
        }
        ';

        /** @var \Illuminate\Cache\RateLimiter $rateLimiter */
        $rateLimiter = $this->app->make(RateLimiter::class);
        $this->assertTrue(method_exists($rateLimiter, 'for'));
        $rateLimiter->for(
            'test',
            static function () {
                // @phpstan-ignore-next-line phpstan ignores markTestSkipped
                return Limit::perMinute(1);
            }
        );

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertJson([
            'errors' => [
                [
                    'message' => RateLimitException::MESSAGE,
                ],
            ],
        ]);
    }

    public function testInlineLimiter(): void
    {
        $this->schema = /** @lang GraphQL */'
        type Query {
            foo: Int @throttle(maxAttempts: 1)
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertJson([
            'errors' => [
                [
                    'message' => RateLimitException::MESSAGE,
                ],
            ],
        ]);
    }
}
