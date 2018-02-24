<?php

namespace Nuwave\Lighthouse\Providers;

use Nuwave\Lighthouse\GraphQL;
use Nuwave\Lighthouse\Schema\Directives\Nodes\ScalarDirective;

use Illuminate\Support\ServiceProvider;

class LighthouseServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerDirectives();
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
        $schema = app('graphql')->stitcher()->stitch(
            config('lighthouse.global_id_field', '_id')
        );
    }

    /**
     * Register Lighthouse directives.
     *
     * @return void
     */
    public function registerDirectives()
    {
        directives()->register('scalar', new ScalarDirective);
    }
}
