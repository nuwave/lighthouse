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
