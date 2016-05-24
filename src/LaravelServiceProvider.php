<?php

namespace Nuwave\Relay;

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
        $this->publishes([__DIR__ . '/../config/config.php' => config_path('relay.php')]);
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'relay');

        if (config('relay.controller')) {
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
    }

    /**
     * Register node type and query.
     *
     * @return void
     */
    protected function registerNodes()
    {
        $graphql = app('graphql');
        $graphql->schema()->type('node', \Nuwave\Relay\Support\Definition\NodeType::class);
        $graphql->schema()->type('pageInfo', \Nuwave\Relay\Support\Definition\PageInfoType::class);
        $graphql->schema()->query('node', \Nuwave\Relay\Support\Definition\NodeQuery::class);
    }

    /**
     * Register schema w/ container.
     *
     * @return void
     */
    protected function registerSchema()
    {
        $schema = $this->app['config']->get('relay.schema.register');

        if (is_callable($schema)) {
            $schema();
        } elseif (is_string($schema)) {
            require_once $schema;
        }
    }
}
