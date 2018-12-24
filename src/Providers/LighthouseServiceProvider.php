<?php

namespace Nuwave\Lighthouse\Providers;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\GraphQL;
use Illuminate\Support\ServiceProvider;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\NodeRegistry;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Execution\ContextFactory;
use Nuwave\Lighthouse\Execution\GraphQLValidator;
use Nuwave\Lighthouse\Schema\Source\SchemaStitcher;
use Nuwave\Lighthouse\Support\Http\Responses\Response;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;
use Nuwave\Lighthouse\Support\Contracts\GraphQLResponse;
use Nuwave\Lighthouse\Schema\Factories\DirectiveFactory;
use Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider;
use Nuwave\Lighthouse\Subscriptions\SubscriptionProvider;
use Nuwave\Lighthouse\Schema\Extensions\ExtensionRegistry;
use Nuwave\Lighthouse\Support\Contracts\CanStreamResponse;
use Nuwave\Lighthouse\Support\Http\Responses\ResponseStream;

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
        ], 'config');

        $this->publishes([
            __DIR__.'/../../assets/default-schema.graphql' => config('lighthouse.schema.register'),
        ], 'schema');

        if (config('lighthouse.controller')) {
            $this->loadRoutesFrom(__DIR__.'/../Support/Http/routes.php');
        }

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

        $this->app->singleton(DirectiveFactory::class);
        $this->app->singleton(ExtensionRegistry::class);
        $this->app->singleton(NodeRegistry::class);
        $this->app->singleton(TypeRegistry::class);
        $this->app->singleton(CreatesContext::class, ContextFactory::class);
        $this->app->singleton(CanStreamResponse::class, ResponseStream::class);
        $this->app->singleton(GraphQLResponse::class, Response::class);

        $this->app->singleton(
            SchemaSourceProvider::class,
            function () {
                return new SchemaStitcher(config('lighthouse.schema.register', ''));
            }
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Nuwave\Lighthouse\Console\ClearCacheCommand::class,
                \Nuwave\Lighthouse\Console\InterfaceCommand::class,
                \Nuwave\Lighthouse\Console\MutationCommand::class,
                \Nuwave\Lighthouse\Console\PrintSchemaCommand::class,
                \Nuwave\Lighthouse\Console\QueryCommand::class,
                \Nuwave\Lighthouse\Console\UnionCommand::class,
                \Nuwave\Lighthouse\Console\ScalarCommand::class,
                \Nuwave\Lighthouse\Console\ValidateSchemaCommand::class,
            ]);
        }

        SubscriptionProvider::register($this->app);
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
                $resolveInfo = Arr::get($customAttributes, 'resolveInfo');

                return $resolveInfo instanceof ResolveInfo
                    ? new GraphQLValidator($translator, $data, $rules, $messages, $customAttributes)
                    : new \Illuminate\Validation\Validator($translator, $data, $rules, $messages, $customAttributes);
            }
        );
    }
}
