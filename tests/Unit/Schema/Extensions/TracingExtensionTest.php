<?php

namespace Tests\Unit\Schema\Extensions;

use Tests\TestCase;
use Nuwave\Lighthouse\Schema\Extensions\TracingExtension;

class TracingExtensionTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('lighthouse.extensions', [TracingExtension::class]);
    }

    protected $schema = <<<SCHEMA
type Query {
    foo: String! @field(resolver: "Tests\\\Unit\\\Schema\\\Extensions\\\TracingExtensionTest@resolve")
}
SCHEMA;

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
                        'resolvers'
                    ]
                ]
            ]
        ]);
    }

    /**
     * @test
     */
    public function itCanAddTracingExtensionMetaToBatchedResults(): void
    {
        $result = $this->postGraphQL([
            ['query' => '{ foo }'],
            ['query' => '{ foo }'],
        ])->assertJsonCount(2)
            ->assertJsonStructure([
                [
                    'extensions' => [
                        'tracing' => [
                            'execution' => [
                                'resolvers'
                            ]
                        ]
                    ],
                ],
                [
                    'extensions' => [
                        'tracing' => [
                            'execution' => [
                                'resolvers'
                            ]
                        ]
                    ]
                ]
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
