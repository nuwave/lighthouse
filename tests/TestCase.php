<?php

namespace Tests;

use Exception;
use GraphQL\Error\Debug;
use GraphQL\Type\Schema;
use Tests\Utils\Middleware\CountRuns;
use Laravel\Scout\ScoutServiceProvider;
use Tests\Utils\Policies\AuthServiceProvider;
use Orchestra\Database\ConsoleServiceProvider;
use Illuminate\Foundation\Testing\TestResponse;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider;
use Nuwave\Lighthouse\Providers\LighthouseServiceProvider;

abstract class TestCase extends BaseTestCase
{
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
     */
    protected function getEnvironmentSetUp($app)
    {
        $app->bind(
            SchemaSourceProvider::class,
            function () {
                return new TestSchemaProvider($this->schema);
            }
        );

        $app['config']->set('lighthouse', [
            'namespaces' => [
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
            ],
            'debug' => Debug::INCLUDE_DEBUG_MESSAGE | Debug::INCLUDE_TRACE /*| Debug::RETHROW_INTERNAL_EXCEPTIONS*/ | Debug::RETHROW_UNSAFE_EXCEPTIONS,
            'subscriptions' => [
                'storage' => 'array',
                'broadcaster' => 'log',
            ],
        ]);

        $app['config']->set('app.debug', true);

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
                }
            };
        });
    }

    protected function tearDown()
    {
        parent::tearDown();

        CountRuns::$runCounter = 0;
    }

    /**
     * Execute a query as if it was sent as a request to the server.
     *
     * @param  string  $query
     * @return \Illuminate\Foundation\Testing\TestResponse
     */
    protected function query(string $query): TestResponse
    {
        return $this->postGraphQL(
            [
                'query' => $query,
            ]
        );
    }

    /**
     * Execute a query as if it was sent as a request to the server.
     *
     * @param  mixed[]  $data
     * @param  mixed[]  $headers
     * @return \Illuminate\Foundation\Testing\TestResponse
     */
    protected function postGraphQL(array $data, array $headers = []): TestResponse
    {
        return $this->postJson(
            'graphql',
            $data,
            $headers
        );
    }

    protected function postGraphQLMultipart(array $data, array $headers = ['content-type' => 'multipart/form-data']): TestResponse
    {
        // JSON encode operations
        if (isset($data['operations'])) {
            $data['operations'] = json_encode($data['operations']);
        }

        // JSON encode map
        if (isset($data['map'])) {
            $data['map'] = json_encode($data['map']);
        }

        return $this->post(
            'graphql',
            $data,
            $headers
        );
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
            $schema
            .$this->placeholderQuery()
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

        return graphql()->prepSchema();
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
}
