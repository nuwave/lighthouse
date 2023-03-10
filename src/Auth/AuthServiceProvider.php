<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Auth;

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(Dispatcher $dispatcher): void
    {
        $dispatcher->listen(RegisterDirectiveNamespaces::class, static fn (): string => __NAMESPACE__);
    }

    /**
     * @return array<string>
     */
    public static function guards(): array
    {
        $config = Container::getInstance()->make(ConfigRepository::class);
        $guards = $config->get('auth.guards');

        $lighthouseGuards = array_filter(
            (array) $config->get('lighthouse.guards', []),
            static fn (string $guard): bool => isset($guards[$guard]),
        );

        return [] !== $lighthouseGuards
            ? $lighthouseGuards
            : [$config->get('auth.defaults.guard')];
    }
}
