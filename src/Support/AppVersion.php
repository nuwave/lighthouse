<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Support;

use Illuminate\Container\Container;
use Illuminate\Support\Str;

class AppVersion
{
    public static function isLumen(): bool
    {
        return Str::contains(self::version(), 'Lumen');
    }

    public static function atLeast(float $version): bool
    {
        return self::versionNumber() >= $version;
    }

    public static function below(float $version): bool
    {
        return self::versionNumber() < $version;
    }

    protected static function version(): string
    {
        /**
         * Not using assert() as only one of those classes will actually be installed.
         *
         * @var \Illuminate\Foundation\Application|\Laravel\Lumen\Application $container
         */
        $container = Container::getInstance();

        return $container->version();
    }

    protected static function versionNumber(): float
    {
        if (self::isLumen()) {
            // Lumen version strings look like: "Lumen (2.3.4)..."
            return (float) Str::after(self::version(), '(');
        }

        // Regular Laravel versions look like: "2.3.4"
        return (float) self::version();
    }
}
