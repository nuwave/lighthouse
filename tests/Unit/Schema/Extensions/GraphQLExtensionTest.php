<?php

namespace Tests\Unit\Schema\Extensions;

use Tests\TestCase;
use Nuwave\Lighthouse\Support\Pipeline;
use Nuwave\Lighthouse\Schema\Extensions\GraphQLExtension;
use Nuwave\Lighthouse\Schema\Extensions\ExtensionRegistry;

class GraphQLExtensionTest extends TestCase
{
    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app->singleton(ExtensionRegistry::class, function () {
            return new class() extends ExtensionRegistry {
                public function __construct()
                {
                    $this->pipeline = app(\Nuwave\Lighthouse\Support\Pipeline::class);
                    $this->extensions = collect([GraphQLExtensionTest::getExtension()]);
                }
            };
        });
    }

    /**
     * @test
     */
    public function itCanManipulateResponseData()
    {
        $this->schema = '
        type Query {
            foo: String
        }';

        $data = $this->queryViaHttp('
        {
            foo
        }
        ');

        $this->assertArrayHasKey('meta', $data);
        $this->assertEquals('data', $data['meta']);
    }

    /**
     * @return GraphQLExtension
     */
    public static function getExtension(): GraphQLExtension
    {
        return new class() extends GraphQLExtension {
            public static function name()
            {
                return 'foo';
            }

            public function willSendResponse(array $response, \Closure $next)
            {
                return $next(array_merge($response, [
                    'meta' => 'data',
                ]));
            }

            public function jsonSerialize()
            {
                return [];
            }
        };
    }
}
