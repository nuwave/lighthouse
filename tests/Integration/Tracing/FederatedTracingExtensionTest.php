<?php declare(strict_types=1);

namespace Tests\Integration\Tracing;

use GraphQL\Error\DebugFlag;
use Illuminate\Contracts\Config\Repository;
use Nuwave\Lighthouse\Federation\FederationServiceProvider;
use Nuwave\Lighthouse\Tracing\FederatedTracing\FederatedTracing;
use Nuwave\Lighthouse\Tracing\FederatedTracing\Proto\Trace;
use Nuwave\Lighthouse\Tracing\TracingServiceProvider;
use Tests\TestCase;

final class FederatedTracingExtensionTest extends TestCase
{
    protected string $schema = /** @lang GraphQL */ '
    type Query {
        foo: Foo!
    }

    type Foo @key(fields: "id") {
        id: String! @field(resolver: "Tests\\\Integration\\\Tracing\\\FederatedTracingExtensionTest@resolve")
        bar: String! @field(resolver: "Tests\\\Integration\\\Tracing\\\FederatedTracingExtensionTest@throw")
    }
    ';

    protected function getPackageProviders($app): array
    {
        return array_merge(
            parent::getPackageProviders($app),
            [FederationServiceProvider::class, TracingServiceProvider::class],
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $config = $this->app->make(Repository::class);
        $config->set('lighthouse.tracing.driver', FederatedTracing::class);

        $config->set('lighthouse.debug', DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE);
    }

    public function testHeaderIsRequiredToEnableTracing(): void
    {
        $response = $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo { id }
            }
            ');

        $response->assertJsonMissingPath('extensions.ftv1');
    }

    public function testAddFtv1ExtensionMetaToResult(): void
    {
        $response = $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo { id }
            }
            ',
                headers: ['apollo-federation-include-trace' => FederatedTracing::V1],
            );

        $response->assertJsonStructure([
            'extensions' => [
                'ftv1',
            ],
        ]);

        $traceData = $this->decodeFtv1Record($response->json('extensions.ftv1'));

        $this->assertArrayHasKey('startTime', $traceData);
        $this->assertArrayHasKey('endTime', $traceData);
        $this->assertArrayHasKey('root', $traceData);
        $this->assertGreaterThan(0, (int) $traceData['durationNs']);
        $this->assertCount(1, $traceData['root']['child']);

        $fooNode = $traceData['root']['child'][0];
        $this->assertSame('foo', $fooNode['responseName']);
        $this->assertSame('Foo!', $fooNode['type']);
        $this->assertSame('Query', $fooNode['parentType']);
        $this->assertArrayHasKey('startTime', $fooNode);
        $this->assertArrayHasKey('endTime', $fooNode);
        $this->assertCount(1, $fooNode['child']);

        $fooIdNode = $fooNode['child'][0];
        $this->assertSame('id', $fooIdNode['responseName']);
        $this->assertSame('String!', $fooIdNode['type']);
        $this->assertSame('Foo', $fooIdNode['parentType']);
        $this->assertArrayHasKey('startTime', $fooIdNode);
        $this->assertArrayHasKey('endTime', $fooIdNode);
        $this->assertArrayNotHasKey('child', $fooIdNode);
    }

    public function testAddFtv1ExtensionMetaToBatchedResults(): void
    {
        $postData = [
            'query' /** @lang GraphQL */ => '
                {
                    foo { id }
                }
                ',
        ];
        $expectedResponse = [
            'extensions' => [
                'ftv1',
            ],
        ];

        $result = $this
            ->postGraphQL(
                [
                    $postData,
                    $postData,
                ],
                headers: ['apollo-federation-include-trace' => FederatedTracing::V1],
            );

        $result->assertJsonCount(2)
            ->assertJsonStructure([
                $expectedResponse,
                $expectedResponse,
            ]);
        $this->assertNotEquals($result->json('0.extensions.ftv1'), $result->json('1.extensions.ftv1'));

        $trace1Data = $this->decodeFtv1Record($result->json('0.extensions.ftv1'));
        $trace2Data = $this->decodeFtv1Record($result->json('1.extensions.ftv1'));

        $startTime1 = $trace1Data['startTime'];
        $endTime1 = $trace1Data['endTime'];

        $startTime2 = $trace2Data['startTime'];
        $endTime2 = $trace2Data['endTime'];

        // Guaranteed by the usleep() in $this->resolve()
        $this->assertGreaterThan($startTime1, $endTime1);

        // Might be the same timestamp if Lighthouse runs quickly in a given environment
        $this->assertGreaterThanOrEqual($endTime1, $startTime2);

        // Guaranteed by the usleep() in $this->resolve()
        $this->assertGreaterThan($startTime2, $endTime2);

        $this->assertCount(1, $trace1Data['root']['child']);
        $this->assertCount(1, $trace2Data['root']['child']);
    }

    public function testReportErrorsToResult(): void
    {
        $response = $this
            ->graphQL(/** @lang GraphQL */ '
            {
                foo { bar }
            }
            ',
                headers: ['apollo-federation-include-trace' => FederatedTracing::V1],
            );

        $response->assertJsonStructure([
            'extensions' => [
                'ftv1',
            ],
        ]);

        $traceData = $this->decodeFtv1Record($response->json('extensions.ftv1'));

        $barNode = $traceData['root']['child'][0]['child'][0];
        $this->assertArrayHasKey('error', $barNode);
        $this->assertCount(1, $barNode['error']);

        $barNodeError = $barNode['error'][0];
        $this->assertSame('Internal server error', $barNodeError['message']);
        $this->assertCount(1, $barNodeError['location']);
        $this->assertArrayHasKey('line', $barNodeError['location'][0]);
        $this->assertArrayHasKey('column', $barNodeError['location'][0]);
        $this->assertArrayNotHasKey('json', $barNodeError);
    }

    public static function resolve(): string
    {
        // Just enough to consistently change the resulting timestamp
        usleep(1000);

        return 'bar';
    }

    /** @return never */
    public static function throw(): void
    {
        throw new \RuntimeException('foo');
    }

    /** @return array<string,mixed> */
    private function decodeFtv1Record(string $encoded): array
    {
        $decoded = \Safe\base64_decode($encoded);

        $trace = new Trace();
        $trace->mergeFromString($decoded);

        return json_decode($trace->serializeToJsonString(), true, 512, JSON_THROW_ON_ERROR);
    }
}
