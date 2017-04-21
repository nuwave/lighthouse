<?php

namespace Nuwave\Lighthouse;

use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\LengthAwarePaginator;
use Nuwave\Lighthouse\Support\Traits\GlobalIdTrait;
use Nuwave\Lighthouse\Support\DataLoader\QueryBuilder;

class LaravelServiceProvider extends ServiceProvider
{
    use GlobalIdTrait;

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([__DIR__.'/../config/config.php' => config_path('lighthouse.php')]);
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'lighthouse');
        $this->loadViewsFrom(realpath(__DIR__.'/Support/Console/Commands/stubs'), 'lighthouse');

        if (config('lighthouse.controller')) {
            require __DIR__.'/Support/Http/routes.php';
        }

        $this->registerNodes();
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

        $this->commands([
            Support\Console\Commands\SchemaCommand::class,
            Support\Console\Commands\FieldMakeCommand::class,
            Support\Console\Commands\MutationMakeCommand::class,
            Support\Console\Commands\QueryMakeCommand::class,
            Support\Console\Commands\TypeMakeCommand::class,
            Support\Console\Commands\ConnectionMakeCommand::class,
            Support\Console\Commands\EdgeMakeCommand::class,
        ]);

        $this->registerMacro();
    }

    /**
     * Register node type and query.
     *
     * @return void
     */
    protected function registerNodes()
    {
        $graphql = app('graphql');
        $graphql->schema()->type('node', \Nuwave\Lighthouse\Support\Definition\NodeType::class);
        $graphql->schema()->type('pageInfo', \Nuwave\Lighthouse\Support\Definition\PageInfoType::class);
        $graphql->schema()->query('node', \Nuwave\Lighthouse\Support\Definition\NodeQuery::class);
    }

    /**
     * Register schema w/ container.
     *
     * @return void
     */
    protected function registerSchema()
    {
        $schema = $this->app['config']->get('lighthouse.schema.register');

        if (is_callable($schema)) {
            $schema();
        } elseif (is_string($schema)) {
            require $schema;
        }
    }

    /**
     * Register pagination macro.
     *
     * @return void
     */
    protected function registerMacro()
    {
        $name = $this->app['config']->get('lighthouse.pagination_macro') ?: 'toConnection';

        $decodeCursor = function (array $args) {
            return $this->decodeCursor($args);
        };

        Collection::macro($name, function (array $args) use ($decodeCursor) {
            $first = isset($args['first']) ? $args['first'] : 15;
            $after = $decodeCursor($args);
            $currentPage = $first && $after ? floor(($first + $after) / $first) : 1;

            return new LengthAwarePaginator(
                collect($this->items)->forPage($currentPage, $first),
                count($this->items),
                $first,
                $currentPage
            );
        });

        Collection::macro('fetch', function ($relations) {
            if (count($this->items) > 0) {
                if (is_string($relations)) {
                    $relations = [$relations];
                }

                $query = $this->first()->newQuery()->with($relations);
                $this->items = (new QueryBuilder)->eagerLoadRelations($query, $this->items);
            }

            return $this;
        });
    }
}
