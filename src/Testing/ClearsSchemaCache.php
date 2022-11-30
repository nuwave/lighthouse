<?php

namespace Nuwave\Lighthouse\Testing;

/**
 * Clears the schema cache once before any tests are run.
 *
 * @mixin \Illuminate\Foundation\Testing\Concerns\InteractsWithConsole
 *
 * @deprecated not safe with parallel testing
 * @see \Nuwave\Lighthouse\Testing\RefreshesSchemaCache
 */
trait ClearsSchemaCache
{
    /** @var bool */
    protected static $schemaCacheWasCleared = false;

    protected function bootClearsSchemaCache(): void
    {
        if (! static::$schemaCacheWasCleared) {
            $this->artisan('lighthouse:clear-cache');
            self::$schemaCacheWasCleared = true;
        }
    }
}
