<?php

namespace Nuwave\Lighthouse\Providers;

use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\GraphQL;

class LighthouseServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // 1. Stitch together schemas
        $this->registerSchema();
        // 2. Parse schema into document node
        // 3. Register Types, Interfaces, Queries, etc...
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('graphql', function () {
            return new GraphQL();
        });

        $this->app->alias('graphql', GraphQL::class);
    }

    /**
     * Register GraphQL schema.
     *
     * @return void
     */
    public function registerSchema()
    {
        $schema = app('graphql')->stitcher()->stitch();
    }
}
