<?php


namespace Nuwave\Lighthouse\Providers;


use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Executor;
use Nuwave\Lighthouse\GraphQL;
use Nuwave\Lighthouse\Schema\DirectiveRegistry;
use Nuwave\Lighthouse\Support\Contracts\SchemaBuilder;

class LighthouseServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register()
    {
        $this->app->bind(
            SchemaBuilder::class,
            config('lighthouse.schema_builder')
        );

        $this->app->bind(
            Executor::class,
            config('lighthouse.executor')
        );

        $this->app->singleton(DirectiveRegistry::class, DirectiveRegistry::class);

        $this->app->singleton(GraphQL::class, function () {
            return new GraphQL(
                app(SchemaBuilder::class),
                app(Executor::class),
                app(DirectiveRegistry::class)
            );
        });

        Collection::mixin(new \Nuwave\Lighthouse\Support\Collection());

        $this->registerDirectives();
    }

    public function registerDirectives()
    {
        graphql()->directives()->load(realpath(__DIR__ . '/../Schema/Directives/'),  'Nuwave\\Lighthouse\\');
    }
}
