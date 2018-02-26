<?php

namespace Nuwave\Lighthouse\Providers;

use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\GraphQL;
use Nuwave\Lighthouse\Schema\Directives\Args\ValidateDirective;

class LighthouseServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
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
     */
    public function registerSchema()
    {
        $schema = app('graphql')->stitcher()->stitch(
            config('lighthouse.global_id_field', '_id')
        );
    }

    /**
     * Register Lighthouse directives.
     */
    public function registerDirectives()
    {
        directives()->register(ValidateDirective::name(), new ValidateDirective());
        directives()->register('can', new \Nuwave\Lighthouse\Schema\Directives\Fields\CanDirective());
        directives()->register('event', new \Nuwave\Lighthouse\Schema\Directives\Fields\EventDirective());
        directives()->register('hasMany', new \Nuwave\Lighthouse\Schema\Directives\Fields\HasManyDirective());
        directives()->register('scalar', new \Nuwave\Lighthouse\Schema\Directives\Nodes\ScalarDirective());
    }
}
