<?php declare(strict_types=1);

namespace Tests;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use GraphQL\Error\DebugFlag;
use GraphQL\Type\Schema;
use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Redis\RedisServiceProvider;
use Laravel\Scout\ScoutServiceProvider as LaravelScoutServiceProvider;
use Nuwave\Lighthouse\Auth\AuthServiceProvider as LighthouseAuthServiceProvider;
use Nuwave\Lighthouse\Cache\CacheServiceProvider;
use Nuwave\Lighthouse\CacheControl\CacheControlServiceProvider;
use Nuwave\Lighthouse\GlobalId\GlobalIdServiceProvider;
use Nuwave\Lighthouse\LighthouseServiceProvider;
use Nuwave\Lighthouse\OrderBy\OrderByServiceProvider;
use Nuwave\Lighthouse\Pagination\PaginationServiceProvider;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
use Nuwave\Lighthouse\Scout\ScoutServiceProvider as LighthouseScoutServiceProvider;
use Nuwave\Lighthouse\SoftDeletes\SoftDeletesServiceProvider;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\MocksResolvers;
use Nuwave\Lighthouse\Testing\TestingServiceProvider;
use Nuwave\Lighthouse\Testing\UsesTestSchema;
use Nuwave\Lighthouse\Validation\ValidationServiceProvider;
use Orchestra\Testbench\TestCase as TestbenchTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Utils\Policies\AuthServiceProvider;

abstract class TestCase extends TestbenchTestCase
{
    use ArraySubsetAsserts;
    use MakesGraphQLRequests;
    use MocksResolvers;
    use UsesTestSchema;

    /**
     * Set when not in setUp.
     *
     * @var \Illuminate\Foundation\Application
     */
    protected $app;

    /** A dummy query type definition that is added to tests by default. */
    public const PLACEHOLDER_QUERY = /** @lang GraphQL */ <<<'GRAPHQL'
type Query {
  foo: Int
}

GRAPHQL;

    protected function setUp(): void
    {
        parent::setUp();

        // This default is only valid for testing Lighthouse itself and thus
        // is not defined in the reusable test trait.
        $this->schema ??= self::PLACEHOLDER_QUERY;
        $this->setUpTestSchema();

        // Using qualifyTestResolver() requires instantiation of the test class through the container.
        // https://laravel.com/docs/container#binding-primitives
        $this->app->when(static::class)
            ->needs('$name')
            ->give('TestName');
    }

    /** @return array<class-string<\Illuminate\Support\ServiceProvider>> */
    protected function getPackageProviders($app): array
    {
        return [
            AuthServiceProvider::class,
            LaravelScoutServiceProvider::class,
            RedisServiceProvider::class,

            // Lighthouse's own
            LighthouseServiceProvider::class,
            LighthouseAuthServiceProvider::class,
            CacheServiceProvider::class,
            CacheControlServiceProvider::class,
            GlobalIdServiceProvider::class,
            LighthouseScoutServiceProvider::class,
            OrderByServiceProvider::class,
            PaginationServiceProvider::class,
            SoftDeletesServiceProvider::class,
            TestingServiceProvider::class,
            ValidationServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
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
            'types' => [
                'Tests\\Utils\\Types',
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
        $config->set(
            'lighthouse.debug',
            DebugFlag::INCLUDE_DEBUG_MESSAGE
            | DebugFlag::INCLUDE_TRACE
            // | Debug::RETHROW_INTERNAL_EXCEPTIONS
            | DebugFlag::RETHROW_UNSAFE_EXCEPTIONS,
        );

        $config->set('lighthouse.guards', null);

        $config->set('lighthouse.subscriptions', [
            'version' => 1,
            'storage' => 'array',
            'broadcaster' => 'log',
        ]);

        $config->set('broadcasting.connections.pusher', [
            'driver' => 'pusher',
            'key' => 'foo',
            'secret' => 'bar',
            'app_id' => 'baz',
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

        $config->set('pennant.default', 'array');

        // Defaults to "algolia", which is not needed in our test setup
        $config->set('scout.driver', null);

        $config->set('lighthouse.federation', [
            'entities_resolver_namespace' => 'Tests\\Utils\\Entities',
        ]);

        $config->set('lighthouse.schema_cache.enable', false);
    }

    /**
     * Rethrow all errors that are not handled by GraphQL.
     *
     * This makes debugging the tests much simpler as Exceptions
     * are fully dumped to the console when making requests.
     */
    protected function resolveApplicationExceptionHandler($app): void
    {
        $app->singleton(ExceptionHandler::class, static fn (): ThrowingExceptionHandler => new ThrowingExceptionHandler());
    }

    /** Build an executable schema from a SDL string, adding on a default Query type. */
    protected function buildSchemaWithPlaceholderQuery(string $schema): Schema
    {
        return $this->buildSchema(
            $schema . self::PLACEHOLDER_QUERY,
        );
    }

    /** Build an executable schema from an SDL string. */
    protected function buildSchema(string $schema): Schema
    {
        $this->schema = $schema;

        $schemaBuilder = $this->app->make(SchemaBuilder::class);

        return $schemaBuilder->schema();
    }

    /** Get a fully qualified reference to a method that is defined on the test class. */
    protected static function qualifyTestResolver(string $method): string
    {
        $escapedClass = addslashes(static::class);

        return "{$escapedClass}@{$method}";
    }

    protected function commandTester(Command $command): CommandTester
    {
        $command->setLaravel($this->app);
        $command->setApplication($this->app->make(ConsoleApplication::class, [
            'version' => $this->app->version(),
        ]));

        return new CommandTester($command);
    }
}
