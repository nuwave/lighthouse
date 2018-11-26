<?php

namespace Nuwave\Lighthouse\Providers;

use Illuminate\Support\Str;
use Nuwave\Lighthouse\GraphQL;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Schema\NodeRegistry;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Execution\ContextFactory;
use Nuwave\Lighthouse\Schema\DirectiveRegistry;
use Nuwave\Lighthouse\Schema\Source\SchemaStitcher;
use Nuwave\Lighthouse\Support\Http\Responses\Response;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;
use Nuwave\Lighthouse\Schema\Factories\DirectiveFactory;
use Nuwave\Lighthouse\Support\Contracts\GraphQLResponse;
use Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider;
use Nuwave\Lighthouse\Schema\Extensions\ExtensionRegistry;
use Nuwave\Lighthouse\Support\Contracts\CanStreamResponse;
use Nuwave\Lighthouse\Support\Http\Responses\ResponseStream;

class LighthouseServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/config.php', 'lighthouse');

        $this->publishes([
            __DIR__.'/../../config/config.php' => config_path('lighthouse.php'),
        ], 'config');

        $this->publishes([
            __DIR__.'/../../assets/default-schema.graphql' => config('lighthouse.schema.register'),
        ], 'schema');

        if (config('lighthouse.controller')) {
            $this->loadRoutesFrom(__DIR__.'/../Support/Http/routes.php');
        }
    }

    /**
     * Load routes from provided path.
     *
     * @param string $path
     */
    protected function loadRoutesFrom($path)
    {
        if (Str::contains($this->app->version(), 'Lumen')) {
            require realpath($path);

            return;
        }

        parent::loadRoutesFrom($path);
    }

    /**
     * Register any application services.
     */
    public function register()
    {
        $this->app->singleton(GraphQL::class);
        $this->app->alias(GraphQL::class, 'graphql');

        $this->app->singleton(DirectiveFactory::class);
        $this->app->singleton(DirectiveRegistry::class);
        $this->app->singleton(ExtensionRegistry::class);
        $this->app->singleton(NodeRegistry::class);
        $this->app->singleton(TypeRegistry::class);
        $this->app->singleton(CreatesContext::class, ContextFactory::class);
        $this->app->singleton(CanStreamResponse::class, ResponseStream::class);
        $this->app->singleton(GraphQLResponse::class, Response::class);

        $this->app->singleton(
            SchemaSourceProvider::class,
            function () {
                return new SchemaStitcher(config('lighthouse.schema.register', ''));
            }
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Nuwave\Lighthouse\Console\ClearCacheCommand::class,
                \Nuwave\Lighthouse\Console\InterfaceCommand::class,
                \Nuwave\Lighthouse\Console\MutationCommand::class,
                \Nuwave\Lighthouse\Console\PrintSchemaCommand::class,
                \Nuwave\Lighthouse\Console\QueryCommand::class,
                \Nuwave\Lighthouse\Console\UnionCommand::class,
                \Nuwave\Lighthouse\Console\ScalarCommand::class,
                \Nuwave\Lighthouse\Console\ValidateSchemaCommand::class,
            ]);
        }
    }
}
