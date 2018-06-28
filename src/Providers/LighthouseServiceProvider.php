<?php

namespace Nuwave\Lighthouse\Providers;

use Illuminate\Support\Str;
use Nuwave\Lighthouse\GraphQL;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Facades\Validator;
use Nuwave\Lighthouse\Schema\Source\SchemaStitcher;
use Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider;
use Nuwave\Lighthouse\Support\Validator\ValidatorFactory;
use Nuwave\Lighthouse\Support\Collection as LighthouseCollection;

class LighthouseServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        $this->publishes([__DIR__.'/../../config/config.php' => config_path('lighthouse.php')]);
        $this->mergeConfigFrom(__DIR__.'/../../config/config.php', 'lighthouse');

        if (config('lighthouse.controller')) {
            $this->loadRoutesFrom(__DIR__.'/../Support/Http/routes.php');
        }

        $this->registerMacros();
        $this->registerValidator();
    }

    /**
     * Load routes from provided path.
     *
     * @param string $path
     */
    protected function loadRoutesFrom($path)
    {
        if (Str::contains($this->app->version(), 'Lumen')) {
            require realpath($path);

            return;
        }

        parent::loadRoutesFrom($path);
    }

    /**
     * Register any application services.
     */
    public function register()
    {
        $this->app->singleton(GraphQL::class);
        $this->app->alias(GraphQL::class, 'graphql');

        $this->app->bind(
            SchemaSourceProvider::class,
            function () {
                return new SchemaStitcher(config('lighthouse.schema.register', ''));
            }
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Nuwave\Lighthouse\Console\ClearCacheCommand::class,
                \Nuwave\Lighthouse\Console\ValidateSchemaCommand::class,
                \Nuwave\Lighthouse\Console\PrintSchemaCommand::class,
            ]);
        }
    }

    /**
     * Register lighthouse macros.
     */
    public function registerMacros()
    {
        Collection::mixin(new LighthouseCollection());
    }

    /**
     * Register graphql validator.
     */
    public function registerValidator()
    {
        // TODO: Check compatibility w/ Lumen
        app(\Illuminate\Validation\Factory::class)->resolver(function (
            $translator,
            $data,
            $rules,
            $messages,
            $customAttributes
        ) {
            return ValidatorFactory::resolve(
                $translator,
                $data,
                $rules,
                $messages,
                $customAttributes
            );
        });

        Validator::extendImplicit('required_with_mutation', ValidatorFactory::class.'@requiredWithMutation');
        Validator::extendImplicit('required_with_query', ValidatorFactory::class.'@requiredWithQuery');
    }
}
