<?php


namespace Nuwave\Lighthouse\Providers;


use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Executor;
use Nuwave\Lighthouse\GraphQL;
use Nuwave\Lighthouse\SchemaBuilder;

class LighthouseServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register()
    {
        $this->app->singleton('graphql', function () {
            return new GraphQL();
        });
        
        $this->app->bind(
            SchemaBuilder::class,
            config('lighthouse.schema_builder')
        );
        
        $this->app->bind(
            Executor::class,
            config('lighthouse.executor')
        );

        Collection::mixin(new \Nuwave\Lighthouse\Support\Collection());

        $this->registerDirectives();
    }

    public function registerDirectives()
    {
        graphql()->directives()->load(realpath(__DIR__ . '/../Schema/Directives/'),  'Nuwave\\Lighthouse\\');
    }
}
