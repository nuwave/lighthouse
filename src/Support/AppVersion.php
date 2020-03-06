<?php

namespace Nuwave\Lighthouse\Support;

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
        return app()->version();
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
