<?php

namespace Tests;

use Exception;
use GraphQL\Error\Debug;
use GraphQL\Type\Schema;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\TestResponse;
use Laravel\Scout\ScoutServiceProvider;
use Nuwave\Lighthouse\GraphQL;
use Nuwave\Lighthouse\LighthouseServiceProvider;
use Nuwave\Lighthouse\OrderBy\OrderByServiceProvider;
use Nuwave\Lighthouse\SoftDeletes\SoftDeletesServiceProvider;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\MocksResolvers;
use Nuwave\Lighthouse\Testing\TestingServiceProvider;
use Nuwave\Lighthouse\Testing\UsesTestSchema;
use Orchestra\Database\ConsoleServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Tests\Utils\Middleware\CountRuns;
use Tests\Utils\Policies\AuthServiceProvider;

abstract class TestCase extends BaseTestCase
{
    use MakesGraphQLRequests;
    use MocksResolvers;
    use UsesTestSchema;

    /**
     * A dummy query type definition that is added to tests by default.
     */
    const PLACEHOLDER_QUERY = /** @lang GraphQL */ '
    type Query {
        foo: Int
    }
    ';

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->schema) {
            $this->schema = self::PLACEHOLDER_QUERY;
        }

        $this->setUpTestSchema();
    }

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return string[]
     */
    protected function getPackageProviders($app): array
    {
        return [
            AuthServiceProvider::class,
            ConsoleServiceProvider::class,
            ScoutServiceProvider::class,
            LighthouseServiceProvider::class,
            SoftDeletesServiceProvider::class,
            OrderByServiceProvider::class,
            TestingServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app): void
    {
        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $app['config'];

        $config->set('lighthouse.namespaces', [
            'models' => [
                'Tests\\Utils\\Models',
                'Tests\\Utils\\ModelsSecondary',
            ],
            'queries' => [
                'Tests\\Utils\\Queries',
                'Tests\\Utils\\QueriesSecondary',
            ],
            'mutations' => [
                'Tests\\Utils\\Mutations',
                'Tests\\Utils\\MutationsSecondary',
            ],
            'subscriptions' => 'Tests\\Utils\\Subscriptions',
            'interfaces' => [
                'Tests\\Utils\\Interfaces',
                'Tests\\Utils\\InterfacesSecondary',
            ],
            'scalars' => [
                'Tests\\Utils\\Scalars',
                'Tests\\Utils\\ScalarsSecondary',
            ],
            'unions' => [
                'Tests\\Utils\\Unions',
                'Tests\\Utils\\UnionsSecondary',
            ],
            'directives' => [
                'Tests\\Utils\\Directives',
            ],
        ]);

        $config->set(
            'lighthouse.debug',
            Debug::INCLUDE_DEBUG_MESSAGE
            | Debug::INCLUDE_TRACE
            /*| Debug::RETHROW_INTERNAL_EXCEPTIONS*/
            | Debug::RETHROW_UNSAFE_EXCEPTIONS
        );

        $config->set(
            'lighthouse.subscriptions',
            [
                'storage' => 'array',
                'broadcaster' => 'log',
            ]
        );

        $config->set('app.debug', true);

        TestResponse::mixin(new TestResponseMixin());
    }

    /**
     * Rethrow all errors that are not handled by GraphQL.
     *
     * This makes debugging the tests much simpler as Exceptions
     * are fully dumped to the console when making requests.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function resolveApplicationExceptionHandler($app)
    {
        $app->singleton(ExceptionHandler::class, function () {
            return new class implements ExceptionHandler {
                public function report(Exception $e) {}

                public function render($request, Exception $e): void
                {
                    throw $e;
                }

                public function renderForConsole($output, Exception $e) {}

                public function shouldReport(Exception $e): bool
                {
                    return false;
                }
            };
        });
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        CountRuns::$runCounter = 0;
    }

    /**
     * Build an executable schema from a SDL string, adding on a default Query type.
     *
     * @param  string  $schema
     * @return \GraphQL\Type\Schema
     */
    protected function buildSchemaWithPlaceholderQuery(string $schema): Schema
    {
        return $this->buildSchema(
            $schema.self::PLACEHOLDER_QUERY
        );
    }

    /**
     * Build an executable schema from an SDL string.
     *
     * @param  string  $schema
     * @return \GraphQL\Type\Schema
     */
    protected function buildSchema(string $schema): Schema
    {
        $this->schema = $schema;

        return $this->app
            ->make(GraphQL::class)
            ->prepSchema();
    }

    /**
     * Get a fully qualified reference to a method that is defined on the test class.
     *
     * @param  string  $method
     * @return string
     */
    protected function qualifyTestResolver(string $method = 'resolve'): string
    {
        return addslashes(static::class).'@'.$method;
    }
}
