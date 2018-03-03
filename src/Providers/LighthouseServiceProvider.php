<?php

namespace Nuwave\Lighthouse\Providers;

use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\GraphQL;

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
        directives()->register(\Nuwave\Lighthouse\Schema\Directives\Args\ValidateDirective::class);
        directives()->register(\Nuwave\Lighthouse\Schema\Directives\Fields\AuthDirective::class);
        directives()->register(\Nuwave\Lighthouse\Schema\Directives\Fields\BelongsTo::class);
        directives()->register(\Nuwave\Lighthouse\Schema\Directives\Fields\CanDirective::class);
        directives()->register(\Nuwave\Lighthouse\Schema\Directives\Fields\EventDirective::class);
        directives()->register(\Nuwave\Lighthouse\Schema\Directives\Fields\FieldDirective::class);
        directives()->register(\Nuwave\Lighthouse\Schema\Directives\Fields\HasManyDirective::class);
        directives()->register(\Nuwave\Lighthouse\Schema\Directives\Fields\MethodDirective::class);
        directives()->register(\Nuwave\Lighthouse\Schema\Directives\Fields\MutationDirective::class);
        directives()->register(\Nuwave\Lighthouse\Schema\Directives\Fields\RenameDirective::class);
        directives()->register(\Nuwave\Lighthouse\Schema\Directives\Nodes\ScalarDirective::class);
    }
}
