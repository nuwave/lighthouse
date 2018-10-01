<?php

namespace Nuwave\Lighthouse\Providers;

use Illuminate\Support\Str;
use Nuwave\Lighthouse\GraphQL;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\NodeRegistry;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Schema\DirectiveRegistry;
use Nuwave\Lighthouse\Schema\MiddlewareRegistry;
use Nuwave\Lighthouse\Execution\GraphQLValidator;
use Nuwave\Lighthouse\Schema\Source\SchemaStitcher;
use Nuwave\Lighthouse\Schema\Factories\ValueFactory;
use Nuwave\Lighthouse\Support\DataLoader\QueryBuilder;
use Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider;
use Nuwave\Lighthouse\Schema\Extensions\ExtensionRegistry;

class LighthouseServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/config.php', 'lighthouse');

        $this->publishes([
            __DIR__.'/../../config/config.php' => config_path('lighthouse.php'),
            __DIR__.'/../../assets/default-schema.graphql' => config('lighthouse.schema.register'),
        ]);

        if (config('lighthouse.controller')) {
            $this->loadRoutesFrom(__DIR__.'/../Support/Http/routes.php');
        }

        $this->registerCollectionMacros();
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

        $this->app->singleton(ValueFactory::class);
        $this->app->singleton(DirectiveRegistry::class);
        $this->app->singleton(ExtensionRegistry::class);
        $this->app->singleton(NodeRegistry::class);
        $this->app->singleton(MiddlewareRegistry::class);
        $this->app->singleton(TypeRegistry::class);

        $this->app->singleton(
            SchemaSourceProvider::class,
            function () {
                return new SchemaStitcher(config('lighthouse.schema.register', ''));
            }
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Nuwave\Lighthouse\Console\UnionCommand::class,
                \Nuwave\Lighthouse\Console\ScalarCommand::class,
                \Nuwave\Lighthouse\Console\InterfaceCommand::class,
                \Nuwave\Lighthouse\Console\ValidateSchemaCommand::class,
                \Nuwave\Lighthouse\Console\PrintSchemaCommand::class,
                \Nuwave\Lighthouse\Console\ClearCacheCommand::class,
            ]);
        }
    }

    /**
     * Register lighthouse macros.
     */
    protected function registerCollectionMacros()
    {
        // TODO remove and just use load() as soon as Laravel fixes https://github.com/laravel/framework/issues/16217
        // This fixes the behaviour of how eager loading queries are built
        Collection::macro('fetch', function ($eagerLoadRelations = null) {
            if (count($this->items) > 0) {
                if (is_string($eagerLoadRelations)) {
                    $eagerLoadRelations = [$eagerLoadRelations];
                }
                $query = $this->first()::with($eagerLoadRelations);
                $this->items = resolve(QueryBuilder::class)->eagerLoadRelations($query, $this->items);
            }

            return $this;
        });

        Collection::macro('fetchCount', function ($eagerLoadRelations = null) {
            if (count($this->items) > 0) {
                if (is_string($eagerLoadRelations)) {
                    $eagerLoadRelations = [$eagerLoadRelations];
                }

                $query = $this->first()::withCount($eagerLoadRelations);
                $this->items = resolve(QueryBuilder::class)->eagerLoadCount($query, $this->items);
            }

            return $this;
        });

        Collection::macro('fetchForPage', function ($perPage, $page, $eagerLoadRelations) {
            if (count($this->items) > 0) {
                if (is_string($eagerLoadRelations)) {
                    $eagerLoadRelations = [$eagerLoadRelations];
                }

                $this->items = $this->fetchCount($eagerLoadRelations)->items;
                $query = $this->first()::with($eagerLoadRelations);
                $this->items = resolve(QueryBuilder::class)
                    ->eagerLoadRelations($query, $this->items, $perPage, $page);
            }

            return $this;
        });
    }

    /**
     * Register GraphQL validator.
     */
    protected function registerValidator()
    {
        $this->app->make(\Illuminate\Validation\Factory::class)->resolver(
            function (
                $translator,
                array $data,
                array $rules,
                array $messages,
                array $customAttributes
            ): \Illuminate\Validation\Validator {
                // This determines whether we are resolving a GraphQL field
                $resolveInfo = array_get($customAttributes, 'resolveInfo');

                return $resolveInfo instanceof ResolveInfo
                    ? new GraphQLValidator($translator, $data, $rules, $messages, $customAttributes)
                    : new \Illuminate\Validation\Validator($translator, $data, $rules, $messages, $customAttributes);
            }
        );

        $this->app['validator']->extendImplicit(
            'required_with_mutation',
            function (string $attribute, $value, array $parameters, GraphQLValidator $validator): bool {
                $info = $validator->getResolveInfo();

                if ('Mutation' !== data_get($info, 'parentType.name')) {
                    return true;
                }

                if (in_array($info->fieldName, $parameters)) {
                    return ! is_null($value);
                }

                return true;
            }
        );
    }
}
