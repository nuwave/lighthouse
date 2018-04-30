<?php

namespace Nuwave\Lighthouse\Providers;

use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Illuminate\Broadcasting\BroadcastManager;
use Nuwave\Lighthouse\GraphQL;
use Nuwave\Lighthouse\Support\DataLoader\QueryBuilder;
use Nuwave\Lighthouse\Support\WebSockets\WebSocketServer;
use Nuwave\Lighthouse\Support\Broadcaster\SubscriptionBroadcaster;
use Illuminate\Foundation\Application;

class LighthouseServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(BroadcastManager $broadcastManager)
    {

        $broadcastManager->extend('lighthouse', function (Application $app, array $config) {
            return new SubscriptionBroadcaster;
        });

        $this->publishes([__DIR__.'/../../config/config.php' => config_path('lighthouse.php')]);
        $this->mergeConfigFrom(__DIR__.'/../../config/config.php', 'lighthouse');

        if (config('lighthouse.controller')) {
            require realpath(__DIR__.'/../Support/Http/routes.php');
        }

        $this->registerSchema();
        $this->registerMacros();
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

        $this->commands([
            \Nuwave\Lighthouse\Support\Console\Commands\CacheCommand::class,
            \Nuwave\Lighthouse\Support\Console\Commands\WebSocketCommand::class,
        ]);

        $this->app->when('Nuwave\Lighthouse\Support\WebSockets\WebSocketController')
            ->needs('Illuminate\Contracts\Auth\UserProvider')
            ->give(function ($app) {
                $auth = $this->app['auth'];
                $guard =  app('config')['lighthouse.auth_guard'] ?: $auth->getDefaultDriver();
                $config = $this->app['config']["auth.guards.{$guard}"];
                return $auth->createUserProvider($config['provider'] ?: null);
            });

        $this->app->when('Nuwave\Lighthouse\Support\WebSockets\WebSocketController')
            ->needs('$resourceServer')
            ->give(function ($app) {
                if (array_key_exists($app, 'League\OAuth2\Server\ResourceServer')) return $app['League\OAuth2\Server\ResourceServer'];
                else return null;
            });

        $this->app->bind('Ratchet\WebSocket\WsServerInterface', function($app){
            return new WebSocketServer($this->app['Nuwave\Lighthouse\Support\WebSockets\WebSocketController']);
        });
    }

    /**
     * Register GraphQL schema.
     */
    public function registerSchema()
    {
        directives()->load(realpath(__DIR__.'/../Schema/Directives/'), 'Nuwave\\Lighthouse\\');
        directives()->load(config('lighthouse.directives', []));

        $schema = app('graphql')->stitcher()->stitch(
            config('lighthouse.global_id_field', '_id'),
            config('lighthouse.schema.register')
        );
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
