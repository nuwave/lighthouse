<?php

namespace Nuwave\Lighthouse\Providers;

use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\GraphQL;
use Nuwave\Lighthouse\Support\DataLoader\QueryBuilder;

class LighthouseServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        $this->registerDirectives();
        $this->registerSchema();
        $this->registerMacros();
        // 2. Parse schema into document node
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
        directives()->register(\Nuwave\Lighthouse\Schema\Directives\Fields\QueryDirective::class);
        directives()->register(\Nuwave\Lighthouse\Schema\Directives\Fields\RenameDirective::class);
        directives()->register(\Nuwave\Lighthouse\Schema\Directives\Nodes\ModelDirective::class);
        directives()->register(\Nuwave\Lighthouse\Schema\Directives\Nodes\ScalarDirective::class);
    }

    /**
     * Register lighthouse macros.
     */
    public function registerMacros()
    {
        Collection::macro('fetch', function ($relations) {
            if (count($this->items) > 0) {
                if (is_string($relations)) {
                    $relations = [$relations];
                }
                $query = $this->first()->newQuery()->with($relations);
                $this->items = app(QueryBuilder::class)->eagerLoadRelations($query, $this->items);
            }

            return $this;
        });

        Collection::macro('fetchCount', function ($relations) {
            if (count($this->items) > 0) {
                if (is_string($relations)) {
                    $relations = [$relations];
                }

                $query = $this->first()->newQuery()->withCount($relations);
                $this->items = app(QueryBuilder::class)->eagerLoadCount($query, $this->items);
            }

            return $this;
        });

        Collection::macro('fetchForPage', function ($perPage, $page, $relations) {
            if (count($this->items) > 0) {
                if (is_string($relations)) {
                    $relations = [$relations];
                }

                $this->items = $this->fetchCount($relations)->items;
                $query = $this->first()->newQuery()->with($relations);
                $this->items = app(QueryBuilder::class)
                    ->eagerLoadRelations($query, $this->items, $perPage, $page);
            }

            return $this;
        });
    }
}
