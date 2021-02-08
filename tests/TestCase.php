<?php

namespace Tests;

use GraphQL\Error\DebugFlag;
use GraphQL\Type\Schema;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Redis\RedisServiceProvider;
use Laravel\Scout\ScoutServiceProvider as LaravelScoutServiceProvider;
use Nuwave\Lighthouse\GlobalId\GlobalIdServiceProvider;
use Nuwave\Lighthouse\GraphQL;
use Nuwave\Lighthouse\LighthouseServiceProvider;
use Nuwave\Lighthouse\OrderBy\OrderByServiceProvider;
use Nuwave\Lighthouse\Pagination\PaginationServiceProvider;
use Nuwave\Lighthouse\Scout\ScoutServiceProvider as LighthouseScoutServiceProvider;
use Nuwave\Lighthouse\SoftDeletes\SoftDeletesServiceProvider;
use Nuwave\Lighthouse\Support\AppVersion;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\MocksResolvers;
use Nuwave\Lighthouse\Testing\UsesTestSchema;
use Nuwave\Lighthouse\Validation\ValidationServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Utils\Policies\AuthServiceProvider;

abstract class TestCase extends BaseTestCase
{
    use MakesGraphQLRequests;
    use MocksResolvers;
    use UsesTestSchema;

    /**
     * A dummy query type definition that is added to tests by default.
     */
    public const PLACEHOLDER_QUERY = /** @lang GraphQL */ <<<'GRAPHQL'
type Query {
  foo: Int
}

GRAPHQL;

    public function setUp(): void
    {
        parent::setUp();

        if ($this->schema === null) {
            $this->schema = self::PLACEHOLDER_QUERY;
        }

        $this->setUpTestSchema();
    }

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<class-string<\Illuminate\Support\ServiceProvider>>
     */
    protected function getPackageProviders($app): array
    {
        return [
            AuthServiceProvider::class,
            LaravelScoutServiceProvider::class,
            RedisServiceProvider::class,

            // Lighthouse's own
            LighthouseServiceProvider::class,
            GlobalIdServiceProvider::class,
            LighthouseScoutServiceProvider::class,
            OrderByServiceProvider::class,
            PaginationServiceProvider::class,
            SoftDeletesServiceProvider::class,
            ValidationServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $app->make(ConfigRepository::class);

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
            'subscriptions' => [
                'Tests\\Utils\\Subscriptions',
            ],
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
            'validators' => [
                'Tests\\Utils\\Validators',
            ],
        ]);

        $config->set('app.debug', true);
        $config->set('lighthouse.debug',
            DebugFlag::INCLUDE_DEBUG_MESSAGE
            | DebugFlag::INCLUDE_TRACE
            // | Debug::RETHROW_INTERNAL_EXCEPTIONS
            | DebugFlag::RETHROW_UNSAFE_EXCEPTIONS
        );

        $config->set('lighthouse.guard', null);

        $config->set('lighthouse.subscriptions', [
            'version' => 1,
            'storage' => 'array',
            'broadcaster' => 'log',
        ]);

        $config->set('database.redis.default', [
            'url' => env('LIGHTHOUSE_TEST_REDIS_URL'),
            'host' => env('LIGHTHOUSE_TEST_REDIS_HOST', 'redis'),
            'password' => env('LIGHTHOUSE_TEST_REDIS_PASSWORD'),
            'port' => env('LIGHTHOUSE_TEST_REDIS_PORT', '6379'),
            'database' => env('LIGHTHOUSE_TEST_REDIS_DB', '0'),
        ]);

        $config->set('database.redis.options', [
            'prefix' => 'lighthouse-test-',
        ]);

        // Defaults to "algolia", which is not needed in our test setup
        $config->set('scout.driver', null);
    }

    /**
     * Rethrow all errors that are not handled by GraphQL.
     *
     * This makes debugging the tests much simpler as Exceptions
     * are fully dumped to the console when making requests.
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function resolveApplicationExceptionHandler($app): void
    {
        $app->singleton(ExceptionHandler::class, function () {
            if (AppVersion::atLeast(7.0)) {
                return new Laravel7ExceptionHandler();
            }

            return new PreLaravel7ExceptionHandler();
        });
    }

    /**
     * Build an executable schema from a SDL string, adding on a default Query type.
     */
    protected function buildSchemaWithPlaceholderQuery(string $schema): Schema
    {
        return $this->buildSchema(
            $schema.self::PLACEHOLDER_QUERY
        );
    }

    /**
     * Build an executable schema from an SDL string.
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
     */
    protected function qualifyTestResolver(string $method = 'resolve'): string
    {
        return addslashes(static::class).'@'.$method;
    }

    /**
     * Construct a command tester.
     */
    protected function commandTester(Command $command): CommandTester
    {
        $command->setLaravel($this->app);

        return new CommandTester($command);
    }
}
