<?php declare(strict_types=1);

namespace Tests\Integration;

use GraphQL\Executor\Executor;
use Tests\TestCase;

final class CustomDefaultResolverTest extends TestCase
{
    public const CUSTOM_RESOLVER_RESULT = 123;

    public function testSpecifyACustomDefaultResolver(): void
    {
        $this->mockResolver([
            'bar' => 'should not be returned',
        ]);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo: Foo @mock
        }

        type Foo {
            bar: Int
        }
        ';

        $previous = Executor::getDefaultFieldResolver();

        Executor::setDefaultFieldResolver(static fn (): int => self::CUSTOM_RESOLVER_RESULT);

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo {
                bar
            }
        }
        ')->assertJson([
            'data' => [
                'foo' => [
                    'bar' => self::CUSTOM_RESOLVER_RESULT,
                ],
            ],
        ]);

        Executor::setDefaultFieldResolver($previous);
    }
}
