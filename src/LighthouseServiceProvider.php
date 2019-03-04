<?php

namespace Nuwave\Lighthouse;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;
use Illuminate\Support\ServiceProvider;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\NodeRegistry;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Console\QueryCommand;
use Nuwave\Lighthouse\Console\UnionCommand;
use Nuwave\Lighthouse\Console\ScalarCommand;
use Illuminate\Contracts\Container\Container;
use Nuwave\Lighthouse\Console\MutationCommand;
use Nuwave\Lighthouse\Console\InterfaceCommand;
use Nuwave\Lighthouse\Execution\ContextFactory;
use Nuwave\Lighthouse\Execution\GraphQLRequest;
use Nuwave\Lighthouse\Console\ClearCacheCommand;
use Nuwave\Lighthouse\Console\PrintSchemaCommand;
use Nuwave\Lighthouse\Execution\GraphQLValidator;
use Nuwave\Lighthouse\Console\SubscriptionCommand;
use Nuwave\Lighthouse\Schema\Source\SchemaStitcher;
use Nuwave\Lighthouse\Console\ValidateSchemaCommand;
use Illuminate\Validation\Factory as ValidationFactory;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;
use Nuwave\Lighthouse\Schema\Factories\DirectiveFactory;
use Nuwave\Lighthouse\Support\Contracts\GraphQLResponse;
use Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider;
use Nuwave\Lighthouse\Support\Contracts\CanStreamResponse;
use Nuwave\Lighthouse\Support\Http\Responses\ResponseStream;

class LighthouseServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @param  \Illuminate\Validation\Factory  $validationFactory
     * @return void
     */
    public function boot(ValidationFactory $validationFactory): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'lighthouse');

        $this->publishes([
            __DIR__.'/../config/config.php' => config_path('lighthouse.php'),
        ], 'config');

        $this->publishes([
            __DIR__.'/../assets/default-schema.graphql' => config('lighthouse.schema.register'),
        ], 'schema');

        if (config('lighthouse.controller')) {
            $this->loadRoutesFrom(__DIR__.'/Support/Http/routes.php');
        }

        $validationFactory->resolver(
            function ($translator, array $data, array $rules, array $messages, array $customAttributes): Validator {
                // This determines whether we are resolving a GraphQL field
                return Arr::get($customAttributes, 'resolveInfo') instanceof ResolveInfo
                    ? new GraphQLValidator($translator, $data, $rules, $messages, $customAttributes)
                    : new Validator($translator, $data, $rules, $messages, $customAttributes);
            }
        );
    }

    /**
     * Load routes from provided path.
     *
     * @param  string  $path
     * @return void
     */
    protected function loadRoutesFrom($path): void
    {
        if (Str::contains($this->app->version(), 'Lumen')) {
            require realpath($path);

            return;
        }

        parent::loadRoutesFrom($path);
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(GraphQL::class);
        $this->app->alias(GraphQL::class, 'graphql');

        $this->app->singleton(DirectiveFactory::class);
        $this->app->singleton(NodeRegistry::class);
        $this->app->singleton(TypeRegistry::class);
        $this->app->singleton(CreatesContext::class, ContextFactory::class);
        $this->app->singleton(CanStreamResponse::class, ResponseStream::class);

        $this->app->bind(GraphQLResponse::class, function (): GraphQLResponse {
            return new class implements GraphQLResponse {
                /**
                 * Create GraphQL response.
                 *
                 * @param  array $data
                 * @return \Symfony\Component\HttpFoundation\Response
                 */
                public function create(array $data)
                {
                    return response($data);
                }
            };
        });

        $this->app->singleton(GraphQLRequest::class, function (Container $app): GraphQLRequest {
            return new GraphQLRequest(
                $app->make('request')
            );
        });

        $this->app->singleton(SchemaSourceProvider::class, function (): SchemaStitcher {
            return new SchemaStitcher(
                config('lighthouse.schema.register', '')
            );
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                ClearCacheCommand::class,
                InterfaceCommand::class,
                MutationCommand::class,
                PrintSchemaCommand::class,
                QueryCommand::class,
                ScalarCommand::class,
                SubscriptionCommand::class,
                UnionCommand::class,
                ValidateSchemaCommand::class,
            ]);
        }
    }
}
