<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Auth\AuthManager;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Events\BuildExtensionsResponse;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;
use Nuwave\Lighthouse\Events\StartExecution;
use Nuwave\Lighthouse\Subscriptions\Contracts\AuthorizesSubscriptions;
use Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions;
use Nuwave\Lighthouse\Subscriptions\Contracts\ContextSerializer;
use Nuwave\Lighthouse\Subscriptions\Contracts\StoresSubscriptions;
use Nuwave\Lighthouse\Subscriptions\Contracts\SubscriptionExceptionHandler;
use Nuwave\Lighthouse\Subscriptions\Contracts\SubscriptionIterator;
use Nuwave\Lighthouse\Subscriptions\Iterators\AuthenticatingSyncIterator;
use Nuwave\Lighthouse\Subscriptions\Iterators\SyncIterator;
use Nuwave\Lighthouse\Subscriptions\Storage\CacheStorageManager;
use Nuwave\Lighthouse\Subscriptions\Storage\RedisStorageManager;
use Nuwave\Lighthouse\Support\Contracts\ProvidesSubscriptionResolver;

class SubscriptionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BroadcastManager::class);
        $this->app->singleton(SubscriptionRegistry::class);
        $this->app->singleton(StoresSubscriptions::class, static function (Container $app): StoresSubscriptions {
            /** @var \Illuminate\Contracts\Config\Repository $configRepository */
            $configRepository = $app->make(ConfigRepository::class);
            switch ($configRepository->get('lighthouse.subscriptions.storage')) {
                case 'redis':
                    return $app->make(RedisStorageManager::class);
                default:
                    return $app->make(CacheStorageManager::class);
            }
        });

        $this->app->bind(ContextSerializer::class, Serializer::class);
        $this->app->bind(AuthorizesSubscriptions::class, Authorizer::class);
        $this->app->bind(SubscriptionIterator::class, SyncIterator::class);
        $this->app->bind(SubscriptionExceptionHandler::class, ExceptionHandler::class);
        $this->app->bind(BroadcastsSubscriptions::class, SubscriptionBroadcaster::class);
        $this->app->bind(ProvidesSubscriptionResolver::class, SubscriptionResolverProvider::class);
    }

    public function boot(EventsDispatcher $eventsDispatcher, ConfigRepository $configRepository): void
    {
        $eventsDispatcher->listen(
            StartExecution::class,
            SubscriptionRegistry::class.'@handleStartExecution'
        );

        $eventsDispatcher->listen(
            BuildExtensionsResponse::class,
            SubscriptionRegistry::class.'@handleBuildExtensionsResponse'
        );

        $eventsDispatcher->listen(
            RegisterDirectiveNamespaces::class,
            static function (): string {
                return __NAMESPACE__.'\\Directives';
            }
        );

        $this->registerBroadcasterRoutes($configRepository);

        // If authentication is used, we can log in subscribers when broadcasting an update
        if ($this->app->bound(AuthManager::class)) {
            config([
                'auth.guards.'.SubscriptionGuard::GUARD_NAME => [
                    'driver' => SubscriptionGuard::GUARD_NAME,
                ],
            ]);

            $this->app->bind(SubscriptionIterator::class, AuthenticatingSyncIterator::class);

            $this->app->make(AuthManager::class)->extend(SubscriptionGuard::GUARD_NAME, static function () {
                return new SubscriptionGuard;
            });
        }
    }

    protected function registerBroadcasterRoutes(ConfigRepository $configRepository): void
    {
        $broadcaster = $configRepository->get('lighthouse.subscriptions.broadcaster');

        if ($routesMethod = $configRepository->get("lighthouse.subscriptions.broadcasters.{$broadcaster}.routes")) {
            [$routesProviderClass, $method] = Str::parseCallback($routesMethod, 'pusher');
            /** @var class-string $routesProviderClass */
            /** @var string $method */
            $routesProvider = $this->app->make($routesProviderClass);
            $router = $this->app->make('router');

            $routesProvider->{$method}($router);
        }
    }
}
