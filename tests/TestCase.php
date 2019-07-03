<?php

namespace Tests;

use Exception;
use GraphQL\Error\Debug;
use GraphQL\Type\Schema;
use Nuwave\Lighthouse\GraphQL;
use Tests\Utils\Middleware\CountRuns;
use Laravel\Scout\ScoutServiceProvider;
use Tests\Utils\Policies\AuthServiceProvider;
use Orchestra\Database\ConsoleServiceProvider;
use Illuminate\Foundation\Testing\TestResponse;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Nuwave\Lighthouse\LighthouseServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider;

abstract class TestCase extends BaseTestCase
{
    use MakesGraphQLRequests;

    /**
     * This variable is injected the main GraphQL class
     * during execution of each test. It may be set either
     * for an entire test class or for a single test.
     *
     * @var string
     */
    protected $schema = '';

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return string[]
     */
    protected function getPackageProviders($app)
    {
        return [
            ScoutServiceProvider::class,
            AuthServiceProvider::class,
            LighthouseServiceProvider::class,
            ConsoleServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app->bind(
            SchemaSourceProvider::class,
            function (): TestSchemaProvider {
                return new TestSchemaProvider($this->schema);
            }
        );

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

        TestResponse::macro(
            'assertErrorCategory',
            function (string $category): TestResponse {
                $this->assertJson([
                    'errors' => [
                        [
                            'extensions' => [
                                'category' => $category,
                            ],
                        ],
                    ],
                ]);

                return $this;
            }
        );

        TestResponse::macro(
            'jsonGet',
            function (string $key = null) {
                return data_get($this->decodeResponseJson(), $key);
            }
        );
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
                /**
                 * Report or log an exception.
                 *
                 * @param  \Exception  $e
                 * @return void
                 */
                public function report(Exception $e)
                {
                    //
                }

                /**
                 * Render an exception into an HTTP response.
                 *
                 * @param  \Illuminate\Http\Request  $request
                 * @param  \Exception  $e
                 * @return void
                 */
                public function render($request, Exception $e): void
                {
                    throw $e;
                }

                /**
                 * Render an exception to the console.
                 *
                 * @param  \Symfony\Component\Console\Output\OutputInterface  $output
                 * @param  \Exception  $e
                 * @return void
                 */
                public function renderForConsole($output, Exception $e)
                {
                    //
                }

                /**
                 * Determine if the exception should be reported.
                 *
                 * @param  \Exception  $e
                 * @return bool
                 */
                public function shouldReport(Exception $e)
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
            $schema.$this->placeholderQuery()
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
     * Convenience method to get a default Query, sometimes needed
     * because the Schema is invalid without it.
     *
     * @return string
     */
    protected function placeholderQuery(): string
    {
        return '
        type Query {
            foo: Int
        }
        ';
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
