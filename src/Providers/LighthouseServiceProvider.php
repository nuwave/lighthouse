<?php


namespace Nuwave\Lighthouse\Providers;


use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\GraphQL;

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

        Collection::mixin(new \Nuwave\Lighthouse\Support\Collection());

        $this->registerDirectives();
    }

    public function registerDirectives()
    {
        graphql()->directives()->load(realpath(__DIR__ . '/../Schema/Directives/'),  'Nuwave\\Lighthouse\\');
    }
}