<?php

namespace Nuwave\Lighthouse;

use Closure;
use Exception;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Application as LaravelApplication;
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
use Nuwave\Lighthouse\Testing\TestingServiceProvider;

class LighthouseServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(ConfigRepository $configRepository): void
    {
        $this->publishes([
            __DIR__.'/lighthouse.php' => $this->app->configPath().'/lighthouse.php',
        ], 'lighthouse-config');

        $this->publishes([
            __DIR__.'/default-schema.graphql' => $configRepository->get('lighthouse.schema.register'),
        ], 'lighthouse-schema');

        $this->loadRoutesFrom(__DIR__.'/Support/Http/routes.php');
    }

    /**
     * Load routes from provided path.
     *
     * @param  string  $path
     */
    protected function loadRoutesFrom($path): void
    {
        if (AppVersion::isLumen()) {
            require \Safe\realpath($path);

            return;
        }

        parent::loadRoutesFrom($path);
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/lighthouse.php', 'lighthouse');

        $this->app->singleton(GraphQL::class);
        $this->app->singleton(ASTBuilder::class);
        $this->app->singleton(DirectiveLocator::class);
        $this->app->singleton(TypeRegistry::class);
        $this->app->singleton(ErrorPool::class);
        $this->app->singleton(CreatesContext::class, ContextFactory::class);
        $this->app->singleton(CanStreamResponse::class, ResponseStream::class);

        $this->app->bind(CreatesResponse::class, SingleResponse::class);

        $this->app->singleton(SchemaSourceProvider::class, function (): SchemaStitcher {
            return new SchemaStitcher(
                config('lighthouse.schema.register', '')
            );
        });

        $this->app->bind(ProvidesResolver::class, ResolverProvider::class);
        $this->app->bind(ProvidesSubscriptionResolver::class, function (): ProvidesSubscriptionResolver {
            return new class implements ProvidesSubscriptionResolver {
                public function provideSubscriptionResolver(FieldValue $fieldValue): Closure
                {
                    throw new Exception(
                        'Add the SubscriptionServiceProvider to your config/app.php to enable subscriptions.'
                    );
                }
            };
        });

        $this->app->bind(ProvidesValidationRules::class, ValidationRulesProvider::class);

        $this->app->singleton(MiddlewareAdapter::class, function (Container $app): MiddlewareAdapter {
            // prefer using fully-qualified class names here when referring to Laravel-only or Lumen-only classes
            if ($app instanceof LaravelApplication) {
                return new LaravelMiddlewareAdapter(
                    $app->get(Router::class)
                );
            }

            if ($app instanceof LumenApplication) {
                return new LumenMiddlewareAdapter($app);
            }

            throw new Exception(
                'Could not correctly determine Laravel framework flavor, got '.get_class($app).'.'
            );
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
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
            ]);
        }

        if ($this->app->runningUnitTests()) {
            $this->app->register(TestingServiceProvider::class);
        }
    }
}
