<?php declare(strict_types=1);

namespace Tests\Integration\Tracing;

use Nuwave\Lighthouse\Tracing\TracingServiceProvider;
use Tests\TestCase;

final class ApolloTracingExtensionTest extends TestCase
{
    protected string $schema = /** @lang GraphQL */ '
    type Query {
        foo: String! @field(resolver: "Tests\\\Integration\\\Tracing\\\ApolloTracingExtensionTest@resolve")
    }
    ';

    protected function getPackageProviders($app): array
    {
        return array_merge(
            parent::getPackageProviders($app),
            [TracingServiceProvider::class],
        );
    }

    public function testAddTracingExtensionMetaToResult(): void
    {
        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo
            }
            ')
            ->assertJsonStructure([
                'extensions' => [
                    'tracing' => [
                        'execution' => [
                            'resolvers',
                        ],
                    ],
                ],
            ]);
    }

    public function testAddTracingExtensionMetaToBatchedResults(): void
    {
        $postData = [
            'query' => /** @lang GraphQL */ '
                {
                    foo
                }
                ',
        ];
        $expectedResponse = [
            'extensions' => [
                'tracing' => [
                    'execution' => [
                        'resolvers',
                    ],
                ],
            ],
        ];

        $result = $this
            ->postGraphQL([
                $postData,
                $postData,
            ])
            ->assertJsonCount(2)
            ->assertJsonStructure([
                $expectedResponse,
                $expectedResponse,
            ]);

        $startTime1 = $result->json('0.extensions.tracing.startTime');
        $endTime1 = $result->json('0.extensions.tracing.endTime');

        $startTime2 = $result->json('1.extensions.tracing.startTime');
        $endTime2 = $result->json('1.extensions.tracing.endTime');

        // Guaranteed by the usleep() in $this->resolve()
        $this->assertGreaterThan($startTime1, $endTime1);

        // Might be the same timestamp if Lighthouse runs quickly in a given environment
        $this->assertGreaterThanOrEqual($endTime1, $startTime2);

        // Guaranteed by the usleep() in $this->resolve()
        $this->assertGreaterThan($startTime2, $endTime2);

        $this->assertCount(1, $result->json('0.extensions.tracing.execution.resolvers'));
        $this->assertCount(1, $result->json('1.extensions.tracing.execution.resolvers'));
    }

    public static function resolve(): string
    {
        // Just enough to consistently change the resulting timestamp
        usleep(1000);

        return 'bar';
    }
}
