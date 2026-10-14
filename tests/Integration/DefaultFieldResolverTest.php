<?php declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Support\Collection;
use Tests\TestCase;

final class DefaultFieldResolverTest extends TestCase
{
    public function testResolvesKeysOfCollection(): void
    {
        $this->mockResolver(new Collection(['present' => 1]));

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            foo: Foo @mock
        }

        type Foo {
            present: Int
            missing: Int
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo {
                present
                missing
            }
        }
        GRAPHQL)->assertExactJson([
            'data' => [
                'foo' => [
                    'present' => 1,
                    'missing' => null,
                ],
            ],
        ]);
    }
}
