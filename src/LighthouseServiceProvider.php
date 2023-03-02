<?php declare(strict_types=1);

namespace Nuwave\Lighthouse;

use GraphQL\Error\ClientAware;
use GraphQL\Error\Error;
use GraphQL\Error\ProvidesExtensions;
use GraphQL\Executor\ExecutionResult;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Laravel\Lumen\Application as LumenApplication;
use Nuwave\Lighthouse\Console\CacheCommand;
use Nuwave\Lighthouse\Console\ClearCacheCommand;
use Nuwave\Lighthouse\Console\DirectiveCommand;
use Nuwave\Lighthouse\Console\IdeHelperCommand;
use Nuwave\Lighthouse\Console\InterfaceCommand;
use Nuwave\Lighthouse\Console\MutationCommand;
use Nuwave\Lighthouse\Console\PrintSchemaCommand;
use Nuwave\Lighthouse\Console\QueryCommand;
use Nuwave\Lighthouse\Console\ScalarCommand;
use Nuwave\Lighthouse\Console\SubscriptionCommand;
use Nuwave\Lighthouse\Console\UnionCommand;
use Nuwave\Lighthouse\Console\ValidateSchemaCommand;
use Nuwave\Lighthouse\Console\ValidatorCommand;
use Nuwave\Lighthouse\Execution\ContextFactory;
use Nuwave\Lighthouse\Execution\ErrorPool;
use Nuwave\Lighthouse\Execution\SingleResponse;
use Nuwave\Lighthouse\Execution\ValidationRulesProvider;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\DirectiveLocator;
use Nuwave\Lighthouse\Schema\ResolverProvider;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
use Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider;
use Nuwave\Lighthouse\Schema\Source\SchemaStitcher;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\AppVersion;
use Nuwave\Lighthouse\Support\Compatibility\LaravelMiddlewareAdapter;
use Nuwave\Lighthouse\Support\Compatibility\LumenMiddlewareAdapter;
use Nuwave\Lighthouse\Support\Compatibility\MiddlewareAdapter;
use Nuwave\Lighthouse\Support\Contracts\CanStreamResponse;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;
use Nuwave\Lighthouse\Support\Contracts\CreatesResponse;
use Nuwave\Lighthouse\Support\Contracts\ProvidesResolver;
use Nuwave\Lighthouse\Support\Contracts\ProvidesSubscriptionResolver;
use Nuwave\Lighthouse\Support\Contracts\ProvidesValidationRules;
use Nuwave\Lighthouse\Support\Http\Responses\ResponseStream;

class LighthouseServiceProvider extends ServiceProvider
{
    /**
     * @var array<int, class-string<\Illuminate\Console\Command>>
     */
    public const COMMANDS = [
        CacheCommand::class,
        ClearCacheCommand::class,
        DirectiveCommand::class,
        IdeHelperCommand::class,
        InterfaceCommand::class,
        MutationCommand::class,
        PrintSchemaCommand::class,
        QueryCommand::class,
        ScalarCommand::class,
        SubscriptionCommand::class,
        UnionCommand::class,
        ValidateSchemaCommand::class,
        ValidatorCommand::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/lighthouse.php', 'lighthouse');

        $this->app->singleton(GraphQL::class);
        $this->app->singleton(ASTBuilder::class);
        $this->app->singleton(SchemaBuilder::class);
        $this->app->singleton(DirectiveLocator::class);
        $this->app->singleton(TypeRegistry::class);
        $this->app->singleton(ErrorPool::class);
        $this->app->singleton(CreatesContext::class, ContextFactory::class);
        $this->app->singleton(CanStreamResponse::class, ResponseStream::class);

        $this->app->bind(CreatesResponse::class, SingleResponse::class);

        $this->app->singleton(SchemaSourceProvider::class, static fn (): SchemaStitcher => new SchemaStitcher(
            config('lighthouse.schema_path', '')
        ));

        $this->app->bind(ProvidesResolver::class, ResolverProvider::class);
        $this->app->bind(ProvidesSubscriptionResolver::class, static fn (): ProvidesSubscriptionResolver => new class() implements ProvidesSubscriptionResolver {
            public function provideSubscriptionResolver(FieldValue $fieldValue): \Closure
            {
                throw new \Exception(
                    'Add the SubscriptionServiceProvider to your config/app.php to enable subscriptions.'
                );
            }
        });

        $this->app->bind(ProvidesValidationRules::class, ValidationRulesProvider::class);

        $this->app->singleton(MiddlewareAdapter::class, static function (Container $app): MiddlewareAdapter {
            if ($app instanceof LaravelApplication) {
                return new LaravelMiddlewareAdapter(
                    $app->get(Router::class)
                );
            }

            if ($app instanceof LumenApplication) {
                return new LumenMiddlewareAdapter();
            }

            $notLaravelOrLumen = $app::class;
            throw new \Exception("Could not correctly determine Laravel framework flavor, got {$notLaravelOrLumen}.");
        });

        $this->commands(self::COMMANDS);
    }

    public function boot(ConfigRepository $configRepository): void
    {
        $this->publishes([
            __DIR__ . '/lighthouse.php' => $this->app->configPath() . '/lighthouse.php',
        ], 'lighthouse-config');

        $this->publishes([
            __DIR__ . '/default-schema.graphql' => $configRepository->get('lighthouse.schema_path'),
        ], 'lighthouse-schema');

        $this->loadRoutesFrom(__DIR__ . '/Support/Http/routes.php');

        $exceptionHandler = $this->app->make(ExceptionHandlerContract::class);
        // @phpstan-ignore-next-line larastan overly eager assumes this will always be a concrete instance
        if ($exceptionHandler instanceof ExceptionHandler) {
            $exceptionHandler->renderable(
                function (ClientAware $error) {
                    assert($error instanceof \Throwable);

                    if (! $error instanceof Error) {
                        $error = new Error(
                            $error->getMessage(),
                            null,
                            null,
                            [],
                            null,
                            $error,
                            $error instanceof ProvidesExtensions ? $error->getExtensions() : []
                        );
                    }

                    $graphQL = $this->app->make(GraphQL::class);
                    $executionResult = new ExecutionResult(null, [$error]);
                    $serializableResult = $graphQL->serializable($executionResult);

                    return new JsonResponse($serializableResult);
                }
            );
        }
    }

    protected function loadRoutesFrom($path): void
    {
        if (AppVersion::isLumen()) {
            require \Safe\realpath($path);

            return;
        }

        parent::loadRoutesFrom($path);
    }
}
