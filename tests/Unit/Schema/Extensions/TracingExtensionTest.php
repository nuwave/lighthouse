<?php

namespace Tests\Unit\Schema\Extensions;

use Tests\TestCase;
use Nuwave\Lighthouse\Schema\Extensions\TracingExtension;

class TracingExtensionTest extends TestCase
{
    protected $schema = <<<SCHEMA
type Query {
    foo: String! @field(resolver: "Tests\\\Unit\\\Schema\\\Extensions\\\TracingExtensionTest@resolve")
}
SCHEMA;

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('lighthouse.extensions', [TracingExtension::class]);
    }

    /**
     * @test
     */
    public function itCanAddTracingExtensionMetaToResult(): void
    {
        $this->query('
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

    /**
     * @test
     */
    public function itCanAddTracingExtensionMetaToBatchedResults(): void
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
                [
                    'extensions' => [
                        'tracing' => [
                            'execution' => [
                                'resolvers',
                            ],
                        ],
                    ],
                ],
            ]);

        $this->assertSame(
            $result->jsonGet('0.extensions.tracing.startTime'),
            $result->jsonGet('1.extensions.tracing.startTime')
        );

        $this->assertNotSame(
            $result->jsonGet('0.extensions.tracing.endTime'),
            $result->jsonGet('1.extensions.tracing.endTime')
        );

        $this->assertCount(1, $result->jsonGet('0.extensions.tracing.execution.resolvers'));
        $this->assertCount(1, $result->jsonGet('1.extensions.tracing.execution.resolvers'));
    }

    public function resolve(): string
    {
        usleep(20000); // 20 milliseconds
        return 'bar';
    }
}
