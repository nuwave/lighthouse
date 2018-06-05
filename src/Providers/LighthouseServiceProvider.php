<?php

namespace Nuwave\Lighthouse\Providers;

use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\GraphQL;
use Nuwave\Lighthouse\Support\Collection as LighthouseCollection;
use Nuwave\Lighthouse\Support\Contracts\GraphQl\Type as TypeInterface;
use Nuwave\Lighthouse\Support\Webonyx\Type;

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


        $this->registerSchema();
        $this->registerMacros();
    }

    protected function loadRoutesFrom($path)
    {
        if(Str::contains($this->app->version(), "Lumen")) {
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
        $this->app->singleton('graphql', function () {
            return new GraphQL();
        });

        $this->app->alias('graphql', GraphQL::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Nuwave\Lighthouse\Support\Console\Commands\CacheCommand::class,
            ]);
        }
    }

    /**
     * Register GraphQL schema.
     */
    public function registerSchema()
    {
        directives()->load(realpath(__DIR__.'/../Schema/Directives/'), 'Nuwave\\Lighthouse\\');
        directives()->load(config('lighthouse.directives', []));

        graphql()->stitcher()->stitch(
            config('lighthouse.global_id_field', '_id'),
            config('lighthouse.schema.register')
        );
    }

    /**
     * Register lighthouse macros.
     */
    public function registerMacros()
    {
        Collection::mixin(new LighthouseCollection());
    }
}
