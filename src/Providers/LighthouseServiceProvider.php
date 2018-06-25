<?php

namespace Nuwave\Lighthouse\Providers;

use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\GraphQL;
use Nuwave\Lighthouse\Support\Collection as LighthouseCollection;

class LighthouseServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        $this->publishes([__DIR__.'/../../config/config.php' => config_path('lighthouse.php')]);
        $this->mergeConfigFrom(__DIR__.'/../../config/config.php', 'lighthouse');

        if (config('lighthouse.controller')) {
            $this->loadRoutesFrom(__DIR__.'/../Support/Http/routes.php');
        }

        $this->registerMacros();
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

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Nuwave\Lighthouse\Console\ClearCacheCommand::class,
                \Nuwave\Lighthouse\Console\ValidateSchemaCommand::class,
                \Nuwave\Lighthouse\Console\PrintSchemaCommand::class,
            ]);
        }
    }

    /**
     * Register lighthouse macros.
     */
    public function registerMacros()
    {
        Collection::mixin(new LighthouseCollection());
    }
}
