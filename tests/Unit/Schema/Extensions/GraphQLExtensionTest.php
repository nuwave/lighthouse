<?php

namespace Tests\Unit\Schema\Extensions;

use Tests\TestCase;
use Nuwave\Lighthouse\Schema\Extensions\GraphQLExtension;
use Nuwave\Lighthouse\Schema\Extensions\ExtensionRegistry;

class GraphQLExtensionTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app->singleton(ExtensionRegistry::class, function (): ExtensionRegistry {
            return new class() extends ExtensionRegistry {
                public function __construct()
                {
                    $this->pipeline = app(\Nuwave\Lighthouse\Support\Pipeline::class);
                    $this->extensions = collect(GraphQLExtensionTest::getExtension());
                }
            };
        });
    }

    /**
     * @test
     */
    public function itCanManipulateResponseData(): void
    {
        $this->schema = '
        type Query {
            foo: String
        }';

        $this->query('
        {
            foo
        }
        ')->assertJson([
            'meta' => 'data'
        ]);
    }

    public static function getExtension(): GraphQLExtension
    {
        return new class() extends GraphQLExtension {
            public static function name(): string
            {
                return 'foo';
            }

            public function willSendResponse(array $response, \Closure $next)
            {
                return $next(array_merge($response, [
                    'meta' => 'data',
                ]));
            }

            public function jsonSerialize(): array
            {
                return [];
            }
        };
    }
}
