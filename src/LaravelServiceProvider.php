<?php

namespace Nuwave\Lighthouse;

use Illuminate\Support\ServiceProvider;

class LaravelServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([__DIR__ . '/../config/config.php' => config_path('lighthouse.php')]);
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'lighthouse');

        if (config('lighthouse.controller')) {
            require_once __DIR__.'/Support/Http/routes.php';
        }

        $this->registerNodes();
        $this->registerSchema();
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('graphql', function ($app) {
            return new GraphQL($app);
        });

        $this->commands([
            Support\Console\Commands\SchemaCommand::class,
            Support\Console\Commands\FieldMakeCommand::class,
            Support\Console\Commands\MutationMakeCommand::class,
            Support\Console\Commands\QueryMakeCommand::class,
            Support\Console\Commands\TypeMakeCommand::class,
        ]);
    }

    /**
     * Register node type and query.
     *
     * @return void
     */
    protected function registerNodes()
    {
        $graphql = app('graphql');
        $graphql->schema()->type('node', \Nuwave\Lighthouse\Support\Definition\NodeType::class);
        $graphql->schema()->type('pageInfo', \Nuwave\Lighthouse\Support\Definition\PageInfoType::class);
        $graphql->schema()->query('node', \Nuwave\Lighthouse\Support\Definition\NodeQuery::class);
    }

    /**
     * Register schema w/ container.
     *
     * @return void
     */
    protected function registerSchema()
    {
        $schema = $this->app['config']->get('lighthouse.schema.register');

        if (is_callable($schema)) {
            $schema();
        } elseif (is_string($schema)) {
            require_once $schema;
        }
    }
}
