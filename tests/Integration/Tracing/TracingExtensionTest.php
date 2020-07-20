<?php

namespace Tests\Integration\Tracing;

use Nuwave\Lighthouse\Tracing\TracingServiceProvider;
use Tests\TestCase;

class TracingExtensionTest extends TestCase
{
    protected $schema = <<<SCHEMA
type Query {
    foo: String! @field(resolver: "Tests\\\Integration\\\Tracing\\\TracingExtensionTest@resolve")
}
SCHEMA;

    protected function getPackageProviders($app): array
    {
        return array_merge(
            parent::getPackageProviders($app),
            [TracingServiceProvider::class]
        );
    }

    public function testCanAddTracingExtensionMetaToResult(): void
    {
        $this->graphQL('
        {
            foo
        }
        ')->assertJsonStructure([
            'extensions' => [
                'tracing' => [
                    'execution' => [
                        'resolvers',
                    ],
                ],
            ],
        ]);
    }

    public function testCanAddTracingExtensionMetaToBatchedResults(): void
    {
        $postData = [
            'query' => '
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
        $result = $this->postGraphQL([
            $postData,
            $postData,
        ])->assertJsonCount(2)
            ->assertJsonStructure([
                $expectedResponse,
                $expectedResponse,
            ]);

        $this->assertSame(
            $result->json('0.extensions.tracing.startTime'),
            $result->json('1.extensions.tracing.startTime')
        );

        $this->assertNotSame(
            $result->json('0.extensions.tracing.endTime'),
            $result->json('1.extensions.tracing.endTime')
        );

        $this->assertCount(1, $result->json('0.extensions.tracing.execution.resolvers'));
        $this->assertCount(1, $result->json('1.extensions.tracing.execution.resolvers'));
    }

    public function resolve(): string
    {
        usleep(20000); // 20 milliseconds

        return 'bar';
    }
}
