<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Subscriptions;

use Illuminate\Auth\AuthManager;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Events\BuildExtensionsResponse;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;
use Nuwave\Lighthouse\Events\StartExecution;
use Nuwave\Lighthouse\Subscriptions\Contracts\AuthorizesSubscriptions;
use Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions;
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
            $configRepository = $app->make(ConfigRepository::class);

            return match ($configRepository->get('lighthouse.subscriptions.storage')) {
                'redis' => $app->make(RedisStorageManager::class),
                default => $app->make(CacheStorageManager::class),
            };
        });

        $this->app->bind(AuthorizesSubscriptions::class, Authorizer::class);
        $this->app->bind(SubscriptionIterator::class, SyncIterator::class);
        $this->app->bind(SubscriptionExceptionHandler::class, ExceptionHandler::class);
        $this->app->bind(BroadcastsSubscriptions::class, SubscriptionBroadcaster::class);
        $this->app->bind(ProvidesSubscriptionResolver::class, SubscriptionResolverProvider::class);
    }

    public function boot(Dispatcher $dispatcher, ConfigRepository $configRepository): void
    {
        $dispatcher->listen(RegisterDirectiveNamespaces::class, static fn (): string => __NAMESPACE__ . '\\Directives');
        $dispatcher->listen(StartExecution::class, SubscriptionRegistry::class . '@handleStartExecution');
        $dispatcher->listen(BuildExtensionsResponse::class, SubscriptionRegistry::class . '@handleBuildExtensionsResponse');

        $this->registerBroadcasterRoutes($configRepository);

        // If authentication is used, we can log in subscribers when broadcasting an update
        if ($this->app->bound(AuthManager::class)) {
            $configRepository->set([
                'auth.guards.' . SubscriptionGuard::GUARD_NAME => [
                    'driver' => SubscriptionGuard::GUARD_NAME,
                ],
            ]);

            $this->app->bind(SubscriptionIterator::class, AuthenticatingSyncIterator::class);

            $this->app->make(AuthManager::class)
                ->extend(SubscriptionGuard::GUARD_NAME, static fn (): SubscriptionGuard => new SubscriptionGuard());
        }
    }

    protected function registerBroadcasterRoutes(ConfigRepository $configRepository): void
    {
        $broadcaster = $configRepository->get('lighthouse.subscriptions.broadcaster');

        if ($routesMethod = $configRepository->get("lighthouse.subscriptions.broadcasters.{$broadcaster}.routes")) {
            [$routesProviderClass, $method] = Str::parseCallback($routesMethod, 'pusher');
            assert(is_string($routesProviderClass) && class_exists($routesProviderClass));
            assert(is_string($method));
            $routesProvider = $this->app->make($routesProviderClass);
            $router = $this->app->make('router');

            $routesProvider->{$method}($router);
        }
    }
}
