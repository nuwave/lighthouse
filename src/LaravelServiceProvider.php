<?php

namespace Nuwave\Lighthouse;

use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\LengthAwarePaginator;
use Nuwave\Lighthouse\Support\Traits\GlobalIdTrait;

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
        $this->publishes([__DIR__ . '/../config/config.php' => config_path('lighthouse.php')]);
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'lighthouse');
        $this->loadViewsFrom(realpath(__DIR__.'/Support/Console/Commands/stubs'), 'lighthouse');

        if (config('lighthouse.controller')) {
            require_once __DIR__.'/Support/Http/routes.php';
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
            require_once $schema;
        }
    }

    /**
     * Register pagination macro.
     *
     * @return void
     */
    protected function registerMacro()
    {
        $name = $this->app['config']->get('lighthouse.controller') ?: 'paginate';

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
    }
}
