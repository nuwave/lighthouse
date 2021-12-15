<?php

namespace Tests\Integration\Tracing;

use Nuwave\Lighthouse\Tracing\TracingServiceProvider;
use Tests\TestCase;

class TracingExtensionTest extends TestCase
{
    protected $schema = /** @lang GraphQL */ '
    type Query {
        foo: String! @field(resolver: "Tests\\\Integration\\\Tracing\\\TracingExtensionTest@resolve")
    }
    ';

    protected function getPackageProviders($app): array
    {
        return array_merge(
            parent::getPackageProviders($app),
            [TracingServiceProvider::class]
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
            'query' => /** @lang GraphQL */'
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

        $this->assertGreaterThan($startTime1, $endTime1);
        $this->assertGreaterThan($endTime1, $startTime2);
        $this->assertGreaterThan($startTime2, $endTime2);

        $this->assertCount(1, $result->json('0.extensions.tracing.execution.resolvers'));
        $this->assertCount(1, $result->json('1.extensions.tracing.execution.resolvers'));
    }

    public function resolve(): string
    {
        usleep(20000); // 20 milliseconds

        return 'bar';
    }
}
