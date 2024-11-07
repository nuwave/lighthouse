<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Tests\TestCase;

final class EnumDirectiveTest extends TestCase
{
    public function testDefinesEnumWithInternalValues(): void
    {
        $this->mockResolver(function ($_, array $args): string {
            $this->assertSame('Active internal', $args['status']);

            return 'Not active';
        });

        $this->schema = /** @lang GraphQL */ '
        enum Status {
            ACTIVE @enum(value: "Active internal")
            INACTIVE @enum(value: "Not active")
        }

        type Query {
            status(status: Status!): Status! @mock
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                status(status: ACTIVE)
            }
            ')
            ->assertExactJson([
                'data' => [
                    'status' => 'INACTIVE',
                ],
            ]);
    }
}
