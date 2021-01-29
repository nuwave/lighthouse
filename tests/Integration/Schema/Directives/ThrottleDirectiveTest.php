<?php

namespace Tests\Integration\Schema\Directives;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Exceptions\RateLimitException;
use Tests\TestCase;
use Tests\Utils\Queries\Foo;

class ThrottleDirectiveTest extends TestCase
{
    public function testWrongLimiterName(): void
    {
        $this->schema = /** @lang GraphQL */
            '
        type Query {
            foo: Int @throttle(name: "test")
        }
        ';

        $this->expectException(DirectiveException::class);
        $this->graphQL(
        /** @lang GraphQL */ '
        {
            foo
        }
        '
        );
    }

    public function testNamedLimiterReturnsRequest(): void
    {
        $this->schema = /** @lang GraphQL */
            '
        type Query {
            foo: Int @throttle(name: "test")
        }
        ';

        RateLimiter::for(
            "test",
            function () {
                return response('Custom response...', 429);
            }
        );

        $this->expectException(DirectiveException::class);
        $this->graphQL(
        /** @lang GraphQL */ '
        {
            foo
        }
        '
        );
    }

    public function testNamedLimiter(): void
    {
        $this->schema = /** @lang GraphQL */'
        type Query {
            foo: Int @throttle(name: "test")
        }
        ';

        RateLimiter::for(
            "test",
            function () {
                return Limit::perMinute(1);
            }
        );

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertJson(
            [
                'data' => [
                    'foo' => Foo::THE_ANSWER
                ]
            ]
        );

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertJson(
            [
                'errors' => [
                    [
                        'message' => RateLimitException::MESSAGE,
                    ]
                ]
            ]
        );
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
        ')->assertJson(
            [
                'data' => [
                    'foo' => Foo::THE_ANSWER
                ]
            ]
        );

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertJson(
            [
                'errors' => [
                    [
                        'message' => RateLimitException::MESSAGE,
                    ]
                ]
            ]
        );
    }

}
